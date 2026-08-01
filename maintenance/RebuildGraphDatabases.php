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

	/**
	 * Reports failure by returning false rather than by exiting, which is what MaintenanceRunner turns
	 * into the process's exit status. A store left out of sync — because its run failed, could not
	 * start, or could not reconcile every page — is a rebuild that cannot be called done.
	 */
	public function execute(): bool {
		$coordinator = NeoWikiExtension::getInstance()->newGraphRebuildCoordinator();
		$storeNames = $this->getStoreNames( $coordinator );

		if ( $storeNames === [] ) {
			$this->outputChanneled( 'No graph stores are configured. There is nothing to rebuild.' );
			return true;
		}

		$storesInSync = 0;

		foreach ( $storeNames as $storeName ) {
			if ( $this->rebuildStore( $coordinator, $storeName ) ) {
				$storesInSync++;
			}
		}

		$this->outputChanneled(
			'Rebuild finished. ' . $storesInSync . ' of ' . count( $storeNames ) . ' stores are in sync.'
		);

		if ( $storesInSync < count( $storeNames ) ) {
			$this->error( 'Not every page was reconciled. The failures are logged on the NeoWiki channel.' );
		}

		return $storesInSync === count( $storeNames );
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
	 * @return bool Whether the store came out of this in sync with the wiki
	 */
	private function rebuildStore( GraphRebuildCoordinator $coordinator, string $storeName ): bool {
		$this->outputChanneled( $storeName . ': starting' );

		try {
			$run = $this->hasOption( 'resume' )
				? $coordinator->resume( $storeName, $this->getRebuildBatchSize(), $this )
				: $coordinator->rebuild( $storeName, RebuildTrigger::Cli, $this->getRebuildBatchSize(), $this );
		} catch ( NothingToResumeException $e ) {
			// Resuming every store passes over the ones that have nothing to continue, since their last
			// rebuild finished. Asking for one store by name and finding nothing to resume is a different
			// thing: the operator asked for something that could not be done.
			$this->outputChanneled( $storeName . ': ' . $e->getMessage() );
			return !$this->hasOption( 'store' );
		} catch ( Exception $e ) {
			$this->outputChanneled( $storeName . ': ' . $e->getMessage() );
			return false;
		}

		$this->reportRun( $run );

		return $run->status === RebuildStatus::Succeeded && $run->failed === 0;
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
	 * A batch of nothing would report a rebuild that reconciled the whole wiki without reading a single
	 * page, so a nonsensical --batch-size becomes the smallest one that makes progress.
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

	public function afterDeletionBatch( RebuildRun $run, int $removedSoFar, int $totalDeleted ): void {
		$this->outputChanneled(
			$run->store . ': ' . $removedSoFar . '/' . $totalDeleted . ' deleted pages removed'
		);
		$this->waitForReplication();
	}

}

$maintClass = RebuildGraphDatabases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
