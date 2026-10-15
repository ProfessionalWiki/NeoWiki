<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndexRebuilder;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Running it again after update.php needs --force, since the update is logged as done.
 */
class RebuildPublishedSubjectIndex extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( DatabasePublishedSubjectIndexRebuilder::DEFAULT_BATCH_SIZE );
		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Rebuilds the published Subject index, which the Subject lists, relation suggestions and Subject ' .
			'counts read, from the revision every page publishes. Run this after merging page histories, which ' .
			'can write a revision the index is not told about.'
		);
	}

	protected function getUpdateKey(): string {
		return 'neowiki-rebuild-published-subject-index';
	}

	protected function doDBUpdates(): bool {
		// update.php runs its post-update scripts even when told to apply no schema changes, so the tables can
		// be missing. Not done, so the run that creates them still fills them.
		if ( !$this->indexTablesExist() ) {
			$this->output( "The NeoWiki published Subject index tables do not exist yet; nothing to rebuild.\n" );
			return false;
		}

		$this->output( "Rebuilding the NeoWiki published Subject index...\n" );

		$reindexed = 0;

		foreach ( $this->newRebuilder()->rebuild() as $reindexed ) {
			$this->output( "...$reindexed pages reindexed\n" );
			$this->waitForReplication();
		}

		$this->output( "Done. Reindexed $reindexed pages.\n" );

		return true;
	}

	private function indexTablesExist(): bool {
		$db = $this->getDB( DB_PRIMARY );

		return $db->tableExists( DatabasePublishedSubjectIndex::SUBJECT_TABLE, __METHOD__ )
			&& $db->tableExists( DatabasePublishedSubjectIndex::RELATION_TABLE, __METHOD__ );
	}

	private function newRebuilder(): DatabasePublishedSubjectIndexRebuilder {
		return new DatabasePublishedSubjectIndexRebuilder(
			db: $this->getPrimaryDB(),
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			revisionPolicy: NeoWikiExtension::getInstance()->getRevisionPolicy(),
			titleFormatter: $this->getServiceContainer()->getTitleFormatter(),
			dependentSubjectRules: NeoWikiExtension::getInstance()->newUnrestrictedDependentSubjectRules(),
			logger: LoggerFactory::getInstance( 'NeoWiki' ),
			batchSize: $this->getBatchSize(),
		);
	}

}

$maintClass = RebuildPublishedSubjectIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
