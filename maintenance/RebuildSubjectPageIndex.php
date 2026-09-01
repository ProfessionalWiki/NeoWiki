<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndexRebuilder;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Rebuilds the subject -> page index from the subject slots it derives from.
 *
 * update.php runs this once, which is what gets a wiki that predates the index a filled one. It is also
 * the repair path afterwards, for a history merge that leaves the source page as a redirect: that writes
 * a revision without firing a hook the index is built on. Such a run needs --force, since the update is
 * logged as done.
 */
class RebuildSubjectPageIndex extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( DatabaseSubjectPageIndexRebuilder::DEFAULT_BATCH_SIZE );
		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Rebuilds the subject -> page index from the Subjects every page currently holds. ' .
			'Run this after merging page histories, which can write a revision the index is not told about.'
		);
	}

	protected function getUpdateKey(): string {
		return 'neowiki-rebuild-subject-page-index-with-headers';
	}

	protected function doDBUpdates(): bool {
		// update.php runs its post-update scripts outside doUpdates(), so this one runs even when the
		// run was told to apply no schema changes: --schema writes the DDL to a file and --noschema
		// skips it entirely. Reported as not done, so the run that does apply the schema still fills
		// the index.
		if ( !$this->indexIsUpToDateWithTheSchema() ) {
			$this->output( "The NeoWiki subject -> page index is not up to date with the schema yet; nothing to rebuild.\n" );
			return false;
		}

		$this->output( "Rebuilding the NeoWiki subject -> page index...\n" );

		// Left at 0 when there is nothing to index, so the closing line is right either way.
		$indexed = 0;

		foreach ( $this->newRebuilder()->rebuild() as $indexed ) {
			$this->output( "...$indexed pages indexed\n" );
			$this->waitForReplication();
		}

		$this->output( "Done. Indexed $indexed pages holding Subjects.\n" );

		return true;
	}

	/**
	 * Both the table and the columns the rebuild writes. Asking only whether the table exists was the
	 * right question while the table was the only schema object here: a skipped CREATE TABLE leaves
	 * nothing to write to. A skipped ALTER does not — the table is there, and writing to it fails on
	 * the first page — so an upgraded wiki has to be recognised too. `nwsp_schema` stands for the
	 * columns the header patch adds, which it adds together, and which the updater registers under
	 * that same column.
	 */
	private function indexIsUpToDateWithTheSchema(): bool {
		$database = $this->getDB( DB_PRIMARY );

		return $database->tableExists( DatabaseSubjectPageIndex::TABLE, __METHOD__ )
			&& $database->fieldExists( DatabaseSubjectPageIndex::TABLE, 'nwsp_schema', __METHOD__ );
	}

	/**
	 * Built from MediaWiki's services alone: update.php runs this on wikis whose NeoWiki configuration
	 * is not readable yet, and nothing here depends on that configuration.
	 */
	private function newRebuilder(): DatabaseSubjectPageIndexRebuilder {
		return new DatabaseSubjectPageIndexRebuilder(
			$this->getPrimaryDB(),
			$this->getServiceContainer()->getRevisionLookup(),
			$this->getBatchSize()
		);
	}

}

$maintClass = RebuildSubjectPageIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
