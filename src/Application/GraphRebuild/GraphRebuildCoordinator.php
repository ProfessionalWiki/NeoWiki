<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use Closure;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;

/**
 * Starts and resumes graph rebuilds.
 *
 * A rebuild is always of one store. Rebuilding every configured store is one run each, run one after
 * another, so an unreachable store costs only its own run: the others still reconcile, and each is
 * resumable on its own. That is also why the run records are per store.
 *
 * Only one run of a store may be going at a time. Two would project over each other and file one
 * store's progress under two rows, leaving neither cursor safe to resume from.
 */
class GraphRebuildCoordinator {

	/**
	 * @param array<string, GraphDatabasePlugin> $stores Keys are store names
	 * @param Closure(GraphDatabasePlugin): SubjectPageRebuilder $newPageRebuilder Builds a rebuilder that
	 *        projects into one store and no other
	 */
	public function __construct(
		private readonly array $stores,
		private readonly RebuildRunRepository $runs,
		private readonly GraphRebuildExecutor $executor,
		private readonly Closure $newPageRebuilder,
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

	/**
	 * @param int<1, max> $batchSize
	 */
	public function rebuild(
		string $storeName,
		RebuildTrigger $trigger,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		$store = $this->getStore( $storeName );
		$this->refuseWhenAlreadyRunning( $storeName );

		return $this->executeRun( $this->runs->startRun( $storeName, $trigger ), $store, $batchSize, $observer );
	}

	/**
	 * Picks the store's last unfinished rebuild back up, continuing from the page it got to. The run is
	 * reopened rather than replaced, so one interrupted rebuild stays one record however often it is
	 * resumed, and its counters keep totalling the whole rebuild.
	 *
	 * @param int<1, max> $batchSize
	 */
	public function resume( string $storeName, int $batchSize, RebuildBatchObserver $observer ): RebuildRun {
		$store = $this->getStore( $storeName );
		$this->refuseWhenAlreadyRunning( $storeName );

		$latestRun = $this->runs->getLatestRun( $storeName );

		if ( $latestRun === null || !$latestRun->status->isResumable() ) {
			throw new NothingToResumeException( $storeName );
		}

		$reopenedRun = $latestRun->reopened();
		$this->runs->updateRun( $reopenedRun );

		return $this->executeRun( $reopenedRun, $store, $batchSize, $observer );
	}

	/**
	 * @param int<1, max> $batchSize
	 */
	private function executeRun(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		int $batchSize,
		RebuildBatchObserver $observer
	): RebuildRun {
		return $this->executor->execute(
			run: $run,
			store: $store,
			pageRebuilder: ( $this->newPageRebuilder )( $store ),
			batchSize: $batchSize,
			observer: $observer
		);
	}

	private function getStore( string $storeName ): GraphDatabasePlugin {
		return $this->stores[$storeName] ?? throw new UnknownGraphStoreException( $storeName, $this->getStoreNames() );
	}

	private function refuseWhenAlreadyRunning( string $storeName ): void {
		$activeRun = $this->runs->getActiveRun( $storeName );

		if ( $activeRun !== null ) {
			throw new RebuildAlreadyRunningException( $activeRun );
		}
	}

}
