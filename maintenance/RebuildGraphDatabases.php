<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use LogicException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class RebuildGraphDatabases extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Rebuilds the graph databases by re-saving every Subject from the latest revision of its page. ' .
			'Useful after a graph database has been wiped or has otherwise drifted from the MediaWiki source of truth.'
		);
	}

	public function execute(): void {
		$this->initializeGraphDatabases();

		$pageIds = $this->getSubjectPageIds();

		$this->outputChanneled( 'Rebuilding graph databases for ' . count( $pageIds ) . ' subject pages...' );

		$rebuilder = NeoWikiExtension::getInstance()->newSubjectPageRebuilder();

		$rebuilt = 0;

		foreach ( $pageIds as $pageId ) {
			if ( $this->rebuildPage( $pageId, $rebuilder ) ) {
				$rebuilt++;
			}
		}

		$this->outputChanneled( "Rebuild finished. Rebuilt $rebuilt of " . count( $pageIds ) . ' pages.' );

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
		$pageIds = NeoWikiExtension::getInstance()->newDeletedSubjectPageIdsLookup()->getDeletedSubjectPageIds();

		if ( $pageIds === [] ) {
			return;
		}

		$this->outputChanneled( 'Removing ' . count( $pageIds ) . ' deleted pages from the graph databases...' );

		$graphDatabasePlugin = NeoWikiExtension::getInstance()->getGraphDatabasePlugin();

		$removed = 0;

		foreach ( $pageIds as $pageId ) {
			if ( $this->removePage( $pageId, $graphDatabasePlugin ) ) {
				$removed++;
			}
		}

		$this->outputChanneled( "Removed $removed of " . count( $pageIds ) . ' deleted pages.' );
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

	private function rebuildPage( int $pageId, SubjectPageRebuilder $rebuilder ): bool {
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

		$this->outputChanneled( "Skipped $name: " . self::skipReason( $outcome ) );
		return false;
	}

	private static function skipReason( PageRefreshOutcome $outcome ): string {
		return match ( $outcome ) {
			PageRefreshOutcome::SkippedMissingRevision => 'no current revision',
			PageRefreshOutcome::SkippedMissingSubjectSlot => 'no subject slot',
			PageRefreshOutcome::Refreshed => throw new LogicException( 'Refreshed is not a skip reason' ),
		};
	}

	/**
	 * @return int[] Page IDs of every page whose latest revision carries the NeoWiki subject slot.
	 */
	private function getSubjectPageIds(): array {
		$services = MediaWikiServices::getInstance();
		$roleId = $services->getSlotRoleStore()->getId( MediaWikiSubjectRepository::SLOT_NAME );

		$rows = $this->getReplicaDB()->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->join( 'slots', null, 'slot_revision_id = page_latest' )
			->where( [ 'slot_role_id' => $roleId ] )
			->orderBy( 'page_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $rows );
	}

}

$maintClass = RebuildGraphDatabases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
