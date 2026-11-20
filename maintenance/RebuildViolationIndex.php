<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Registration\ExtensionRegistry;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildViolationIndexJob;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseViolationIndexRebuilder;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * update.php runs it once per NeoWiki version; running it again for the same version needs --force.
 */
class RebuildViolationIndex extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( DatabaseViolationIndexRebuilder::DEFAULT_BATCH_SIZE );
		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Checks the Subjects every page publishes against their Schemas and records their Violations, ' .
			'in jobs unless told to do it now. Run this after enabling or disabling an extension that adds ' .
			'Property Types, since that can change which values are valid without changing a page.'
		);
		$this->addOption( 'now', 'Check the pages in this script rather than in jobs' );
	}

	protected function getUpdateKey(): string {
		return 'neowiki-rebuild-violation-index-' . ExtensionRegistry::getInstance()->getAllThings()['NeoWiki']['version'];
	}

	protected function doDBUpdates(): bool {
		// update.php runs its post-update scripts even when told to apply no schema changes, so the table can
		// be missing. Not done, so the run that creates it still fills it.
		if ( !$this->getDB( DB_PRIMARY )->tableExists( DatabaseViolationIndex::TABLE, __METHOD__ ) ) {
			$this->output( "The NeoWiki Violation index table does not exist yet; nothing to rebuild.\n" );
			return false;
		}

		if ( !$this->hasOption( 'now' ) ) {
			$this->getServiceContainer()->getJobQueueGroup()->push( RebuildViolationIndexJob::newSpecification() );
			$this->output( "Queued jobs that check the Subjects of every page for Violations.\n" );
			return true;
		}

		$this->output( "Checking the Subjects of every page for Violations...\n" );

		$checked = 0;

		foreach ( $this->newRebuilder()->rebuild() as $checked ) {
			$this->output( "...$checked pages checked\n" );
			$this->waitForReplication();
		}

		$this->output( "Done. Checked $checked pages.\n" );

		return true;
	}

	private function newRebuilder(): DatabaseViolationIndexRebuilder {
		return new DatabaseViolationIndexRebuilder(
			db: $this->getPrimaryDB(),
			checker: NeoWikiExtension::getInstance()->newPageViolationChecker(),
			batchSize: $this->getBatchSize(),
		);
	}

}

$maintClass = RebuildViolationIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
