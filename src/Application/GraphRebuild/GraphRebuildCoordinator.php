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
 * Starting a run of a store that already has one is refused: two would project over each other and
 * file one store's progress under two rows, leaving neither cursor safe to resume from. The check reads
 * the run records rather than holding a lock, so it stops the case it is meant to — a second rebuild
 * started while one is under way — and not two started in the same instant.
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
		// Built before the run is recorded as started, because building it can fail on its own — a
		// backend whose configuration will not resolve — and a store must not be left with a run nothing
		// ever ran, which every later rebuild of it then refuses to start alongside.
		$pageRebuilder = ( $this->newPageRebuilder )( $store );

		return $this->executor->execute(
			run: $this->runs->startRun( $storeName, $trigger ),
			store: $store,
			pageRebuilder: $pageRebuilder,
			batchSize: $batchSize,
			observer: $observer
		);
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
		$pageRebuilder = ( $this->newPageRebuilder )( $store );

		$latestRun = $this->runs->getLatestRun( $storeName );

		if ( $latestRun === null || !$latestRun->status->isResumable() ) {
			throw new NothingToResumeException( $storeName, $latestRun );
		}

		$reopenedRun = $latestRun->reopened();
		$this->runs->updateRun( $reopenedRun );

		return $this->executor->execute(
			run: $reopenedRun,
			store: $store,
			pageRebuilder: $pageRebuilder,
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
