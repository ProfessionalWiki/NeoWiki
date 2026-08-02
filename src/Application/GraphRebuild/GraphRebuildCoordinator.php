<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use Closure;
use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Persistence\RebuildStartLock;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Starts, continues, resumes and cancels graph rebuilds.
 *
 * A rebuild is always of one store. Rebuilding every configured store is one run each, run one after
 * another, so an unreachable store costs only its own run: the others still reconcile, and each is
 * resumable and cancellable on its own. That is also why the run records are per store.
 *
 * Where a rebuild runs is the caller's choice, not the run's: {@see self::rebuild()} does the whole
 * thing before returning, which is what a maintenance script wants, and {@see self::startBackground()}
 * files it for the job queue to work through, which is what a web request wants. They produce the same
 * kind of run and share every record, so a store has one rebuild whichever surface asked for it.
 *
 * Starting a run of a store that already has one is refused: two would project over each other and file
 * one store's progress under two rows, leaving neither cursor safe to resume from. The check and the row
 * it guards are taken under the store's start lock, so two callers starting in the same instant produce
 * one run and one refusal rather than two runs.
 */
class GraphRebuildCoordinator {

	/**
	 * How many pages a background batch projects. Sized for a job that finishes well inside a job
	 * runner's patience rather than for throughput: the queue holds no fewer pages either way. Overridable
	 * only as a test seam, so a test can drive a rebuild over several batches without a wiki of that size.
	 */
	public const int BACKGROUND_BATCH_SIZE = 200;

	/**
	 * @param array<string, GraphDatabasePlugin> $stores Keys are store names
	 * @param Closure(GraphDatabasePlugin): SubjectPageRebuilder $newPageRebuilder Builds a rebuilder that
	 *        projects into one store and no other
	 * @param int<1, max> $backgroundBatchSize
	 */
	public function __construct(
		private readonly array $stores,
		private readonly RebuildRunRepository $runs,
		private readonly RebuildStartLock $startLock,
		private readonly GraphRebuildExecutor $executor,
		private readonly RebuildJobQueue $jobQueue,
		private readonly Closure $newPageRebuilder,
		private readonly LoggerInterface $logger,
		private readonly int $backgroundBatchSize = self::BACKGROUND_BATCH_SIZE,
	) {
	}

	/**
	 * Every configured store, in the order a rebuild of all of them runs.
	 *
	 * @return string[]
	 */
	public function getStoreNames(): array {
		return array_keys( $this->stores );
	}

	public function hasStore( string $storeName ): bool {
		return isset( $this->stores[$storeName] );
	}

	/**
	 * Rebuilds the store here and now, returning the run as it ended.
	 *
	 * @param int<1, max> $batchSize
	 */
	public function rebuild(
		string $storeName,
		RebuildTrigger $trigger,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		self::refuseAnEmptyBatch( $batchSize );
		$store = $this->getStore( $storeName );
		// Built before the run is recorded as started, because building it can fail on its own — a
		// backend whose configuration will not resolve — and a store must not be left with a run nothing
		// ever ran, which every later rebuild of it then refuses to start alongside.
		$pageRebuilder = ( $this->newPageRebuilder )( $store );

		$run = $this->startLock->whileHeld( $storeName, function () use ( $storeName, $trigger ): RebuildRun {
			$this->refuseWhenAlreadyActive( $storeName );

			return $this->runs->startRun( $storeName, $trigger, RebuildStatus::Running );
		} );

		return $this->executor->execute(
			run: $run,
			store: $store,
			pageRebuilder: $pageRebuilder,
			batchSize: $batchSize,
			observer: $observer
		);
	}

	/**
	 * Files a rebuild of the store for the job queue to work through, and returns the run as queued.
	 * Nothing has been projected when this returns; the run records say how far it has got.
	 */
	public function startBackground( string $storeName, RebuildTrigger $trigger ): RebuildRun {
		$this->getStore( $storeName );

		$run = $this->startLock->whileHeld( $storeName, function () use ( $storeName, $trigger ): RebuildRun {
			$this->refuseWhenAlreadyActive( $storeName );

			return $this->runs->startRun( $storeName, $trigger, RebuildStatus::Queued );
		} );

		try {
			$this->jobQueue->pushRebuildBatch( $run->id, $storeName );
		} catch ( Throwable $e ) {
			// A queued run nothing will ever pick up is worse than no run at all: it blocks every later
			// rebuild of the store until someone edits the table. Recorded as ended, then re-thrown so
			// the caller reports the failure rather than a rebuild that is under way.
			$this->runs->updateRun( $run->failed( BackendFailureMessage::withoutCredentials( $e->getMessage() ) ) );
			throw $e;
		}

		return $run;
	}

	/**
	 * Runs one batch of a background rebuild and queues the next, if the run has one left. Called once
	 * per job, so this is where a run that has since been cancelled, or finished, stops.
	 *
	 * Nothing is thrown out of here: a job that fails is retried, and a retry of a batch that ended its
	 * run reads the record and stops. The failure is recorded on the run and on the log, which is where
	 * an operator looks for it.
	 */
	public function continueInBackground( int $runId, string $storeName ): void {
		$run = $this->runs->getRun( $runId );

		if ( $run === null || $run->status->isTerminal() ) {
			return;
		}

		try {
			$store = $this->getStore( $storeName );

			$run = $this->executor->executeOneBatch(
				run: $run,
				store: $store,
				pageRebuilder: ( $this->newPageRebuilder )( $store ),
				batchSize: $this->backgroundBatchSize,
				observer: new NullRebuildBatchObserver()
			);
		} catch ( Throwable $e ) {
			$this->endCrashedRun( $run, $e );
			return;
		}

		if ( !$run->status->isTerminal() ) {
			$this->jobQueue->pushRebuildBatch( $runId, $storeName );
		}
	}

	/**
	 * Everything the executor itself can go wrong at is already recorded on the run by the time it
	 * returns. This covers what surrounds it — an unconfigured store, an unresolvable backend — so that
	 * no background rebuild can leave a run recorded as going with nothing going.
	 */
	private function endCrashedRun( RebuildRun $run, Throwable $e ): void {
		$reason = BackendFailureMessage::withoutCredentials( $e->getMessage() );
		$this->runs->updateRun( $run->failed( $reason === '' ? null : $reason ) );

		$this->logger->error(
			'NeoWiki background graph rebuild of store "' . $run->store . '" could not run a batch, so the '
			. 'run was ended. Underlying error: ' . $reason,
			[ 'exception' => $e, 'store' => $run->store ]
		);
	}

	/**
	 * Ends the store's rebuild, wherever it is running. A queued run is dropped where it lies; a running
	 * one stops at its next batch boundary, because that is where every driver re-reads the record.
	 */
	public function cancel( string $storeName ): RebuildRun {
		$this->getStore( $storeName );

		$activeRun = $this->runs->getActiveRun( $storeName );

		if ( $activeRun === null ) {
			throw new NothingToCancelException( $storeName );
		}

		$cancelledRun = $activeRun->cancelled();
		$this->runs->updateRun( $cancelledRun );

		return $cancelledRun;
	}

	/**
	 * Picks the store's last unfinished rebuild back up, continuing from the page it got to, in the phase
	 * it got to. The run is reopened rather than replaced, so one interrupted rebuild stays one record
	 * however often it is resumed, and its counters keep totalling the whole rebuild.
	 *
	 * @param int<1, max> $batchSize
	 */
	public function resume( string $storeName, int $batchSize, RebuildBatchObserver $observer ): RebuildRun {
		self::refuseAnEmptyBatch( $batchSize );
		$store = $this->getStore( $storeName );
		$pageRebuilder = ( $this->newPageRebuilder )( $store );

		$reopenedRun = $this->startLock->whileHeld( $storeName, function () use ( $storeName ): RebuildRun {
			$this->refuseWhenAlreadyActive( $storeName );

			$latestRun = $this->runs->getLatestRun( $storeName );

			if ( $latestRun === null || !$latestRun->status->isResumable() ) {
				throw new NothingToResumeException( $storeName, $latestRun );
			}

			$reopenedRun = $latestRun->started();
			$this->runs->updateRun( $reopenedRun );

			return $reopenedRun;
		} );

		return $this->executor->execute(
			run: $reopenedRun,
			store: $store,
			pageRebuilder: $pageRebuilder,
			batchSize: $batchSize,
			observer: $observer
		);
	}

	/**
	 * A batch of nothing reads no page, so the walk over the wiki would never advance and the run would
	 * end reporting a reconciled wiki it never touched. Checked rather than left to the parameter type,
	 * which only binds the callers that are statically analysed.
	 */
	private static function refuseAnEmptyBatch( int $batchSize ): void {
		if ( $batchSize < 1 ) {
			throw new InvalidArgumentException(
				'A rebuild batch must hold at least one page, not ' . $batchSize . '.'
			);
		}
	}

	private function getStore( string $storeName ): GraphDatabasePlugin {
		return $this->stores[$storeName] ?? throw new UnknownGraphStoreException( $storeName, $this->getStoreNames() );
	}

	private function refuseWhenAlreadyActive( string $storeName ): void {
		$activeRun = $this->runs->getActiveRun( $storeName );

		if ( $activeRun !== null ) {
			throw new RebuildAlreadyRunningException( $activeRun );
		}
	}

}
