<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use User;

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

		$handler = NeoWikiExtension::getInstance()->getStoreContentUC();
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$user = $this->getMaintenanceUser();

		$rebuilt = 0;

		foreach ( $pageIds as $pageId ) {
			if ( $this->rebuildPage( $pageId, $handler, $wikiPageFactory, $user ) ) {
				$rebuilt++;
			}
		}

		$this->outputChanneled( "Rebuild finished. Rebuilt $rebuilt of " . count( $pageIds ) . ' pages.' );
	}

	private function rebuildPage(
		int $pageId,
		OnRevisionCreatedHandler $handler,
		WikiPageFactory $wikiPageFactory,
		UserIdentity $user
	): bool {
		$title = Title::newFromID( $pageId );

		if ( $title === null ) {
			$this->outputChanneled( "Skipped page $pageId: title not found" );
			return false;
		}

		$revision = $wikiPageFactory->newFromTitle( $title )->getRevisionRecord();

		if ( $revision === null ) {
			$this->outputChanneled( 'Skipped ' . $title->getPrefixedText() . ': no current revision' );
			return false;
		}

		$handler->onRevisionCreated( $revision, $user );
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

	private function getMaintenanceUser(): User {
		return User::newSystemUser( 'NeoWiki', [ 'steal' => true ] );
	}

}

$maintClass = RebuildGraphDatabases::class;
require_once RUN_MAINTENANCE_IF_MAIN;
