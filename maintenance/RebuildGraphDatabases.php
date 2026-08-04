<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use Maintenance;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\PageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class RebuildGraphDatabases extends Maintenance {

	private const int PROGRESS_INTERVAL = 500;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Rebuilds the graph databases by re-projecting every page on the wiki from its latest ' .
			'revision. Useful after a graph database has been wiped or has otherwise drifted from the ' .
			'MediaWiki source of truth.'
		);
		$this->addOption(
			'from-page-id',
			'Resume after this page id, as reported by the progress output, instead of starting at the '
			. 'first page. Only affects the re-projection; deleted pages are always reconciled in full.',
			false,
			true
		);
	}

	public function execute(): void {
		$this->initializeGraphDatabases();

		$this->outputChanneled( 'Rebuilding graph databases...' );

		$rebuilder = NeoWikiExtension::getInstance()->newPageRebuilder();
		$afterPageId = (int)$this->getOption( 'from-page-id', 0 );

		$rebuilt = 0;
		$total = 0;

		foreach ( NeoWikiExtension::getInstance()->newPageIdsLookup()->getPageIds( $afterPageId ) as $pageId ) {
			$total++;

			if ( $this->rebuildPage( $pageId, $rebuilder ) ) {
				$rebuilt++;
			}

			if ( $total % self::PROGRESS_INTERVAL === 0 ) {
				$this->outputChanneled( "... $total pages so far, last page id $pageId" );

				// Projecting a page parses it and writes to every backend, so a whole-wiki rebuild runs
				// long enough to matter to the replicas. Yielding here is also what lets the load
				// balancer let go of a replica that has been taken out of the pool.
				$this->waitForReplication();
			}
		}

		$this->outputChanneled( "Rebuild finished. Rebuilt $rebuilt of $total pages." );

		$this->removeDeletedPages();
	}

	/**
	 * Initializing the backends before re-projecting guarantees a rebuilt graph carries any store-level
	 * structures they need (e.g. uniqueness constraints). The rebuild is the production path that
	 * (re)establishes the graph from the MediaWiki source of truth, so it is the natural, idempotent
	 * point to ensure those structures exist (#874).
	 */
	private function initializeGraphDatabases(): void {
		$this->outputChanneled( 'Initializing graph databases...' );
		NeoWikiExtension::getInstance()->getGraphDatabasePlugin()->initialize();
	}

	/**
	 * Re-saving the pages that still exist cannot undo a projection delete that failed, so a page deleted
	 * while a backend was unreachable would otherwise stay in the graph for good, its Subjects still
	 * queryable. Re-issue the delete for every page MediaWiki no longer has. Deleting a page that is
	 * already absent is a no-op, so this is safe to repeat.
	 */
	private function removeDeletedPages(): void {
		$graphDatabasePlugin = NeoWikiExtension::getInstance()->getGraphDatabasePlugin();

		// Announced before the walk rather than after it: this reads the whole archive table, which on a
		// wiki with a long deletion history is a lot of work to spend without saying anything.
		$this->outputChanneled( 'Removing deleted pages from the graph databases...' );

		$removed = 0;
		$total = 0;

		foreach ( NeoWikiExtension::getInstance()->newDeletedPageIdsLookup()->getDeletedPageIds() as $pageId ) {
			$total++;

			if ( $this->removePage( $pageId, $graphDatabasePlugin ) ) {
				$removed++;
			}

			if ( $total % self::PROGRESS_INTERVAL === 0 ) {
				$this->waitForReplication();
			}
		}

		// A page id the archive yields more than once is counted more than once, so this counts
		// deletions handled rather than distinct pages. Removing an already absent page is a no-op.
		$this->outputChanneled( "Removed $removed of $total deletions from the graph databases." );
	}

	private function removePage( int $pageId, GraphDatabasePlugin $graphDatabasePlugin ): bool {
		try {
			$graphDatabasePlugin->deletePage( new PageId( $pageId ) );
		}
		catch ( Exception $e ) {
			$this->outputChanneled( "Failed to remove deleted page $pageId: " . $e->getMessage() );
			return false;
		}

		return true;
	}

	private function rebuildPage( int $pageId, PageRebuilder $rebuilder ): bool {
		$title = Title::newFromID( $pageId );

		if ( $title === null ) {
			$this->outputChanneled( "Skipped page $pageId: title not found" );
			return false;
		}

		$name = $title->getPrefixedText();

		try {
			$outcome = $rebuilder->rebuild( $title );
		}
		catch ( Exception $e ) {
			$this->outputChanneled( "Failed $name: " . $e->getMessage() );
			return false;
		}

		if ( $outcome === PageRefreshOutcome::Refreshed ) {
			$this->outputChanneled( "Rebuilt $name" );
			return true;
		}

		$this->outputChanneled( "Skipped $name: " . $outcome->skipReason() );
		return false;
	}

}

$maintClass = RebuildGraphDatabases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
