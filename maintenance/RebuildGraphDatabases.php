<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use Maintenance;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NothingToResumeException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Reconciles the graph stores with the wiki, one store per run.
 *
 * The script is a shell over {@see GraphRebuildCoordinator}: it decides which stores to rebuild and
 * where the progress is printed, and the coordinator does the rebuilding. It observes its own runs, so
 * each batch reports progress and waits for the replicas before the next one is read.
 */
class RebuildGraphDatabases extends Maintenance implements RebuildBatchObserver {

	private const DEFAULT_BATCH_SIZE = GraphRebuildCoordinator::BACKGROUND_BATCH_SIZE;

	/**
	 * How many of a store's failing pages the report names before pointing at the log for the rest.
	 */
	private const REPORTED_FAILED_PAGES = 5;

	/**
	 * @var int[] The pages the store now being rebuilt could not reconcile
	 */
	private array $failedPageIds = [];

	/**
	 * Pages this invocation has removed from the store now being rebuilt. Counted here rather than read
	 * off the run, because a resumed run's earlier removals happened in another process.
	 */
	private int $removedPages = 0;

	/**
	 * What the store now being rebuilt has to get through in each phase, for the progress lines to count
	 * against. Counted once per store: each is a scan of the wiki, and a rebuild is many batches. The
	 * cost is that a wiki edited under a long rebuild is measured against the size it started at.
	 */
	private int $totalPages = 0;
	private int $totalDeletedPages = 0;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Rebuilds the graph databases by re-projecting every page on the wiki from its latest revision. ' .
			'Useful after a graph database has been wiped or has otherwise drifted from the MediaWiki source of truth.'
		);
		$this->addOption(
			'store',
			'Rebuild only this graph store, by its configured name. Defaults to every configured store, ' .
			'rebuilt one after another.',
			false,
			true
		);
		$this->addOption(
			'resume',
			"Continue each store's last unfinished rebuild from the page it got to, instead of starting over."
		);
		$this->setBatchSize( self::DEFAULT_BATCH_SIZE );
	}

	/**
	 * Reports failure by returning false rather than by exiting, which is what MaintenanceRunner turns
	 * into the process's exit status. A store left out of sync — because its run failed, could not
	 * start, or could not reconcile every page — is a rebuild that cannot be called done.
	 */
	public function execute(): bool {
		$batchSize = $this->getRebuildBatchSize();

		if ( $batchSize === null ) {
			$this->error( '--batch-size must be a whole number of pages, and at least 1.' );
			return false;
		}

		$coordinator = NeoWikiExtension::getInstance()
			->newGraphRebuildCoordinator( GraphRebuildCoordinator::BACKGROUND_BATCH_SIZE );
		$storeNames = $this->getStoreNames( $coordinator );

		if ( $storeNames === [] ) {
			$this->outputChanneled( 'No graph stores are configured. There is nothing to rebuild.' );
			return true;
		}

		$outOfSyncStores = [];

		foreach ( $storeNames as $storeName ) {
			if ( !$this->rebuildStore( $coordinator, $storeName, $batchSize ) ) {
				$outOfSyncStores[] = $storeName;
			}
		}

		$this->outputChanneled(
			'Rebuild finished. ' . ( count( $storeNames ) - count( $outOfSyncStores ) ) . ' of '
			. count( $storeNames ) . ' graph stores are in sync with the wiki.'
		);

		if ( $outOfSyncStores === [] ) {
			return true;
		}

		// Named rather than counted, because what an operator does next is per store: rebuild this one,
		// resume that one. Why each is out of sync was reported as it happened.
		$this->error( 'Still out of sync: ' . implode( ', ', $outOfSyncStores ) . '.' );

		return false;
	}

	/**
	 * Store names are array keys, and PHP turns a numeric one into an int on the way in.
	 *
	 * @return string[]
	 */
	private function getStoreNames( GraphRebuildCoordinator $coordinator ): array {
		$requestedStore = $this->getOption( 'store' );

		return $requestedStore === null
			? array_map( 'strval', $coordinator->getStoreNames() )
			: [ (string)$requestedStore ];
	}

	/**
	 * A store whose rebuild cannot start, or that fails partway, must not stop the stores queued after
	 * it: scoping a run to one store is what makes each store independently recoverable.
	 *
	 * @param int<1, max> $batchSize
	 *
	 * @return bool Whether the store came out of this in sync with the wiki
	 */
	private function rebuildStore( GraphRebuildCoordinator $coordinator, string $storeName, int $batchSize ): bool {
		$this->outputChanneled( $storeName . ': starting' );
		$this->failedPageIds = [];
		$this->removedPages = 0;
		$this->countWhatThereIsToDo();

		try {
			$run = $this->hasOption( 'resume' )
				? $coordinator->resume( $storeName, RebuildTrigger::Cli, $batchSize, $this )
				: $coordinator->rebuild( $storeName, RebuildTrigger::Cli, $batchSize, $this );
		} catch ( NothingToResumeException $e ) {
			return $this->reportNothingToResume( $storeName, $e );
		} catch ( Exception $e ) {
			$this->reportStoreFailure( $storeName, $e->getMessage() );
			return false;
		}

		$this->reportRun( $run );

		return self::isReconciled( $run );
	}

	/**
	 * Resuming every store passes over the ones already reconciled, since their last rebuild finished
	 * with nothing left. A store that has never been rebuilt, or whose last run left pages behind, also
	 * has nothing to continue but is not in sync. Asking for one store by name is a different thing
	 * again: the operator asked for something that could not be done.
	 *
	 * @return bool Whether the store is in sync with the wiki despite having nothing to resume
	 */
	private function reportNothingToResume( string $storeName, NothingToResumeException $e ): bool {
		if ( !$this->hasOption( 'store' ) && self::isReconciled( $e->latestRun ) ) {
			$this->outputChanneled( $storeName . ': ' . $e->getMessage() );
			return true;
		}

		$this->reportStoreFailure( $storeName, $e->getMessage() );
		return false;
	}

	/**
	 * To stderr, so a scheduled rebuild's failure reaches whoever reads that rather than only the log
	 * file its output went to.
	 */
	private function reportStoreFailure( string $storeName, string $reason ): void {
		$this->error( $storeName . ': ' . $reason );
	}

	/**
	 * A store is in sync with the wiki only when a rebuild of it both finished and reconciled every page.
	 */
	private static function isReconciled( ?RebuildRun $run ): bool {
		return $run !== null && $run->status === RebuildStatus::Succeeded && $run->failed === 0;
	}

	private function reportRun( RebuildRun $run ): void {
		$this->outputChanneled(
			$run->store . ': ' . $run->status->value . '. Projected ' . $run->processed . ' pages, '
			. $run->failed . ' failed.'
		);

		if ( $this->failedPageIds !== [] ) {
			$this->reportStoreFailure( $run->store, $this->describeFailedPages() );
		}

		if ( $run->status !== RebuildStatus::Failed ) {
			return;
		}

		if ( $run->error !== null ) {
			$this->reportStoreFailure( $run->store, $run->error );
		}

		$this->outputChanneled(
			$run->store . ': re-run with --resume to continue from page ' . $run->cursor . '.'
		);
	}

	/**
	 * Names enough of the failing pages to see what they have in common, and points at the log for the
	 * rest: a store rejecting the whole wiki must not bury the summary under its page ids.
	 */
	private function describeFailedPages(): string {
		$named = array_slice( $this->failedPageIds, 0, self::REPORTED_FAILED_PAGES );
		$rest = count( $this->failedPageIds ) - count( $named );

		return 'failed on pages ' . implode( ', ', $named )
			. ( $rest === 0 ? '' : ' and ' . $rest . ' more' )
			. '. The NeoWiki log channel says why.';
	}

	/**
	 * How many pages to project between recordings. Read from the raw option rather than through
	 * Maintenance, which turns anything unparseable into 0: a batch of nothing would report a rebuild
	 * that reconciled the whole wiki without reading a single page, and rounding one up to 1 would walk
	 * the wiki a page at a time under an option the operator got wrong, saying nothing about it.
	 *
	 * @return int<1, max>|null Null when --batch-size is not a whole number of at least one page
	 */
	private function getRebuildBatchSize(): ?int {
		$requested = $this->getOption( 'batch-size' );

		if ( $requested === null ) {
			return self::DEFAULT_BATCH_SIZE;
		}

		if ( !ctype_digit( (string)$requested ) ) {
			return null;
		}

		$batchSize = (int)$requested;

		return $batchSize < 1 ? null : $batchSize;
	}

	private function countWhatThereIsToDo(): void {
		$extension = NeoWikiExtension::getInstance();

		$this->totalPages = $extension->newPageIdsLookup()->countPages();
		$this->totalDeletedPages = $extension->newDeletedPageIdsLookup()->countDeletedPages();
	}

	public function pageFailed( int $pageId ): void {
		$this->failedPageIds[] = $pageId;
	}

	public function afterPageBatch( RebuildRun $run ): void {
		$this->outputChanneled(
			$run->store . ': ' . $run->processed . '/' . $this->totalPages
			. ' pages (failed ' . $run->failed . ')'
		);
		$this->waitForReplication();
	}

	public function afterDeletionBatch( RebuildRun $run, int $removedInBatch ): void {
		$this->removedPages += $removedInBatch;

		$this->outputChanneled(
			$run->store . ': ' . $this->removedPages . '/' . $this->totalDeletedPages
			. ' deleted pages removed'
		);
		$this->waitForReplication();
	}

}

$maintClass = RebuildGraphDatabases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
