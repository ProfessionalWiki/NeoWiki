<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectPageRebuilder;
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
		$pageIds = $this->getSubjectPageIds();

		$this->outputChanneled( 'Rebuilding graph databases for ' . count( $pageIds ) . ' subject pages...' );

		$rebuilder = new SubjectPageRebuilder(
			NeoWikiExtension::getInstance()->getStoreContentUC(),
			MediaWikiServices::getInstance()->getWikiPageFactory()
		);

		$rebuilt = 0;

		foreach ( $pageIds as $pageId ) {
			if ( $this->rebuildPage( $pageId, $rebuilder ) ) {
				$rebuilt++;
			}
		}

		$this->outputChanneled( "Rebuild finished. Rebuilt $rebuilt of " . count( $pageIds ) . ' pages.' );
	}

	private function rebuildPage( int $pageId, SubjectPageRebuilder $rebuilder ): bool {
		$title = Title::newFromID( $pageId );

		if ( $title === null ) {
			$this->outputChanneled( "Skipped page $pageId: title not found" );
			return false;
		}

		if ( !$rebuilder->rebuild( $title ) ) {
			$this->outputChanneled( 'Skipped ' . $title->getPrefixedText() . ': no current revision' );
			return false;
		}

		$this->outputChanneled( 'Rebuilt ' . $title->getPrefixedText() );
		return true;
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
