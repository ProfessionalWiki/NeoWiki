<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use Maintenance;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
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

	private const DEFAULT_BATCH_SIZE = 200;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Rebuilds the graph databases by re-saving every Subject from the latest revision of its page. ' .
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

	public function execute(): void {
		$coordinator = NeoWikiExtension::getInstance()->newGraphRebuildCoordinator();
		$storeNames = $this->getStoreNames( $coordinator );

		if ( $storeNames === [] ) {
			$this->outputChanneled( 'No graph stores are configured. There is nothing to rebuild.' );
			return;
		}

		$runs = [];

		foreach ( $storeNames as $storeName ) {
			$runs[] = $this->rebuildStore( $coordinator, $storeName );
		}

		$this->reportOutcome( $runs );
	}

	/**
	 * @return string[]
	 */
	private function getStoreNames( GraphRebuildCoordinator $coordinator ): array {
		$requestedStore = $this->getOption( 'store' );

		return $requestedStore === null ? $coordinator->getStoreNames() : [ (string)$requestedStore ];
	}

	/**
	 * A store whose rebuild cannot start, or that fails partway, must not stop the stores queued after
	 * it: scoping a run to one store is what makes each store independently recoverable. Returns null
	 * when no run happened at all.
	 */
	private function rebuildStore( GraphRebuildCoordinator $coordinator, string $storeName ): ?RebuildRun {
		$this->outputChanneled( $storeName . ': starting' );

		try {
			$run = $this->hasOption( 'resume' )
				? $coordinator->resume( $storeName, $this->getRebuildBatchSize(), $this )
				: $coordinator->rebuild( $storeName, RebuildTrigger::Cli, $this->getRebuildBatchSize(), $this );
		} catch ( Exception $e ) {
			$this->outputChanneled( $storeName . ': ' . $e->getMessage() );
			return null;
		}

		$this->reportRun( $run );

		return $run;
	}

	/**
	 * A batch of nothing would walk the wiki forever without projecting a page, so a nonsensical
	 * --batch-size becomes the smallest one that makes progress.
	 *
	 * @return int<1, max>
	 */
	private function getRebuildBatchSize(): int {
		return max( 1, $this->getBatchSize() ?? self::DEFAULT_BATCH_SIZE );
	}

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void {
		$this->outputChanneled(
			$run->store . ': ' . $run->processed . '/' . $totalPages . ' pages (failed ' . $run->failed . ')'
		);
		$this->waitForReplication();
	}

	public function afterDeletionBatch( RebuildRun $run, int $removed, int $totalDeleted ): void {
		$this->outputChanneled(
			$run->store . ': ' . $removed . '/' . $totalDeleted . ' deleted pages removed'
		);
		$this->waitForReplication();
	}

	private function reportRun( RebuildRun $run ): void {
		$this->outputChanneled(
			$run->store . ': ' . $run->status->value . '. Projected ' . $run->processed . ' pages, '
			. $run->failed . ' failed.'
		);

		if ( $run->error !== null ) {
			$this->outputChanneled( $run->store . ': ' . $run->error );
			$this->outputChanneled(
				$run->store . ': re-run with --resume to continue from page ' . $run->cursor . '.'
			);
		}
	}

	/**
	 * Exits non-zero when anything was left unreconciled — a store that failed or never started, or a
	 * page within a store that did — so a scheduled rebuild cannot fail silently.
	 *
	 * @param array<int, RebuildRun|null> $runs One entry per store, null where no run happened
	 */
	private function reportOutcome( array $runs ): void {
		$rebuiltStores = 0;
		$failedPages = 0;

		foreach ( $runs as $run ) {
			if ( $run !== null && $run->status === RebuildStatus::Succeeded ) {
				$rebuiltStores++;
			}

			$failedPages += $run?->failed ?? 0;
		}

		$this->outputChanneled(
			'Rebuild finished. ' . $rebuiltStores . ' of ' . count( $runs ) . ' stores rebuilt, '
			. $failedPages . ' pages failed.'
		);

		if ( $rebuiltStores < count( $runs ) || $failedPages > 0 ) {
			$this->fatalError( 'Not every page was reconciled. The failures are logged on the NeoWiki channel.' );
		}
	}

}

$maintClass = RebuildGraphDatabases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
