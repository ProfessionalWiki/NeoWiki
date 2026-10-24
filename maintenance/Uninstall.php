<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Exception;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\Application\Uninstall\GraphStoreClearer;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\GraphRebuildJob;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildLastEditorPagesJob;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\MediaWikiPageDeleter;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\NeoWikiTables;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\NeoWikiNamespacePurger;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\SubjectSlotRemover;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\UninstallSurvey;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class Uninstall extends Maintenance {

	public const array JOB_TYPES = [
		GraphRebuildJob::TYPE,
		RebuildLastEditorPagesJob::TYPE,
	];

	private const int DEFAULT_BATCH_SIZE = 500;

	/**
	 * How many pages a report names before pointing elsewhere for the rest.
	 */
	private const int LISTED_PAGES = 20;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Removes all NeoWiki data from the wiki, so that it keeps working once NeoWiki is removed. ' .
			'Without --delete, only reports what it would remove.'
		);
		$this->addOption( 'delete', 'Remove the data. This cannot be undone, so back up the database first.' );
		$this->setBatchSize( self::DEFAULT_BATCH_SIZE );
	}

	/**
	 * Stops, leaving NeoWiki in place, at the first step that does not complete, so that running it again
	 * once the cause is fixed finishes the job. In particular a graph store is cleared before the pages it
	 * holds are purged from the wiki, since a later run could not find them any more.
	 */
	public function execute(): bool {
		if ( $this->getBatchSize() < 1 ) {
			$this->error( '--batch-size must be a whole number of at least 1.' );
			return false;
		}

		$this->reportWhatWouldBeRemoved();

		if ( !$this->hasOption( 'delete' ) ) {
			$this->output(
				"\nNothing was removed. Back up your database, then run this again with --delete to remove all of the above.\n"
			);
			return true;
		}

		if ( !$this->graphStoresCanBeReached() ) {
			return false;
		}

		$this->deletePagesInNeoWikiNamespaces();
		$this->deleteQueuedJobs();

		if ( !$this->clearGraphStores() ) {
			return false;
		}

		$this->purgeDeletedRevisionsInNeoWikiNamespaces();
		$this->removeSubjectsFromRevisions();

		if ( !$this->nothingIsLeft() ) {
			return false;
		}

		$this->dropTables();
		$this->reportNextSteps();

		return true;
	}

	private function reportWhatWouldBeRemoved(): void {
		$survey = $this->newSurvey();

		$this->output( "NeoWiki data on this wiki:\n" );
		$this->outputFigure( 'Pages holding Subjects', $survey->countPagesHoldingSubjects() );
		$this->outputFigure( 'Revisions holding Subjects, counting old and deleted ones', $survey->countRevisionsHoldingSubjects() );
		$this->outputFigure( 'Pages in NeoWiki namespaces', $survey->countPagesInNeoWikiNamespaces() );
		$this->outputFigure( 'Deleted revisions in NeoWiki namespaces', $survey->countDeletedRevisionsInNeoWikiNamespaces() );
		$this->outputFigure( 'Graph stores', self::listOrNone( array_keys( $this->getGraphStores() ) ) );
		$this->outputFigure( 'Database tables', self::listOrNone( $this->newTables()->getExisting() ) );
	}

	private function outputFigure( string $label, int|string $value ): void {
		$this->output( "  $label: $value\n" );
	}

	/**
	 * @param array<int|string> $items
	 */
	private static function listOrNone( array $items ): string {
		return $items === [] ? 'none' : implode( ', ', $items );
	}

	/**
	 * Checked before anything changes: deleting a page also takes it out of every store, which waits out a
	 * timeout on each page when a store cannot be reached.
	 */
	private function graphStoresCanBeReached(): bool {
		$allReached = true;

		foreach ( $this->getGraphStores() as $storeName => $store ) {
			try {
				$store->initialize();
			} catch ( Exception $e ) {
				$this->reportStoreFailure( (string)$storeName, $e );
				$allReached = false;
			}
		}

		return $allReached;
	}

	private function reportStoreFailure( string $storeName, Exception $e ): void {
		$this->output(
			"\nCould not reach graph store $storeName: " . BackendFailureMessage::withoutCredentials( $e->getMessage() )
			. "\nNothing more was removed. Run this again once the store can be reached.\n"
		);
	}

	private function deletePagesInNeoWikiNamespaces(): void {
		$this->output( "\nDeleting the pages in NeoWiki namespaces...\n" );
		$this->outputProgress(
			$this->newNamespacePurger()->deletePages( function ( string $page, string $reason ): void {
				$this->output( "Could not delete $page: $reason\n" );
			} ),
			'pages deleted'
		);
	}

	/**
	 * @param iterable<int> $counts How far a step has got, once per batch
	 */
	private function outputProgress( iterable $counts, string $unit ): void {
		foreach ( $counts as $count ) {
			$this->output( "...$count $unit\n" );
			$this->waitForReplication();
		}
	}

	/**
	 * After the page deletions, which can queue a graph store rebuild, and before the stores are cleared,
	 * so that no queued job puts pages back into a cleared store.
	 */
	private function deleteQueuedJobs(): void {
		$jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();

		foreach ( self::JOB_TYPES as $type ) {
			$jobQueueGroup->get( $type )->delete();
		}
	}

	private function clearGraphStores(): bool {
		$clearer = $this->newGraphStoreClearer();

		foreach ( $this->getGraphStores() as $storeName => $store ) {
			$this->output( "\nClearing graph store $storeName...\n" );

			try {
				foreach ( $clearer->clear( $store ) as $removed ) {
					$this->output( "...$removed pages removed\n" );
				}
			} catch ( Exception $e ) {
				$this->reportStoreFailure( (string)$storeName, $e );
				return false;
			}
		}

		return true;
	}

	private function purgeDeletedRevisionsInNeoWikiNamespaces(): void {
		$this->output( "\nPurging the deleted revisions in NeoWiki namespaces...\n" );
		$this->outputProgress( $this->newNamespacePurger()->purgeDeletedRevisions(), 'revisions purged' );
	}

	private function removeSubjectsFromRevisions(): void {
		$this->output( "\nRemoving the Subjects from every revision...\n" );
		$this->outputProgress( $this->newSubjectSlotRemover()->remove(), 'revisions' );
	}

	/**
	 * A page that could not be deleted, or a Subject saved while this ran, would fail once NeoWiki is gone.
	 */
	private function nothingIsLeft(): bool {
		$survey = $this->newSurvey();
		$remainingPages = $survey->getPagesInNeoWikiNamespaces( self::LISTED_PAGES + 1 );
		$remainingRevisions = $survey->countRevisionsHoldingSubjects();

		if ( $remainingPages === [] && $remainingRevisions === 0 ) {
			return true;
		}

		if ( $remainingPages !== [] ) {
			$this->output( "\nThese pages in NeoWiki namespaces could not be deleted:\n" );
			$this->outputPages( $remainingPages, 'Special:AllPages' );
		}

		if ( $remainingRevisions > 0 ) {
			$this->output( "\n$remainingRevisions revisions still hold Subjects, saved while this ran.\n" );
		}

		$this->output( "NeoWiki stays installed. Delete those pages yourself, or stop edits, then run this again.\n" );

		return false;
	}

	/**
	 * @param string[] $pages One more than are listed, to tell whether there are more
	 */
	private function outputPages( array $pages, string $whereTheRestAre ): void {
		foreach ( array_slice( $pages, 0, self::LISTED_PAGES ) as $page ) {
			$this->output( "  $page\n" );
		}

		if ( count( $pages ) > self::LISTED_PAGES ) {
			$this->output( "  ... and more in $whereTheRestAre\n" );
		}
	}

	private function dropTables(): void {
		$this->output( "\nDropping NeoWiki's database tables...\n" );
		$this->newTables()->drop();
	}

	private function reportNextSteps(): void {
		$this->output(
			"\nNeoWiki's data is removed. Pages fail while NeoWiki is still loaded, so finish uninstalling now:\n" .
			"  1. Remove wfLoadExtension( 'NeoWiki' ) and the NeoWiki settings from LocalSettings.php, then restart PHP.\n" .
			"  2. Delete the extensions/NeoWiki directory, remove it from composer.local.json if you listed it there,\n" .
			"     then run composer update.\n"
		);
	}

	protected function newTables(): NeoWikiTables {
		return new NeoWikiTables( $this->getPrimaryDB(), NeoWikiTables::NAMES );
	}

	/**
	 * @return array<string|int, GraphDatabasePlugin> Keys are store names
	 */
	private function getGraphStores(): array {
		return NeoWikiExtension::getInstance()->getNamedGraphDatabasePlugins();
	}

	private function newSurvey(): UninstallSurvey {
		return new UninstallSurvey( $this->getPrimaryDB() );
	}

	private function newGraphStoreClearer(): GraphStoreClearer {
		$extension = NeoWikiExtension::getInstance();

		return new GraphStoreClearer(
			pageIdsLookup: $extension->newPageIdsLookup(),
			deletedPageIdsLookup: $extension->newDeletedPageIdsLookup(),
			batchSize: $this->getBatchSize()
		);
	}

	private function newNamespacePurger(): NeoWikiNamespacePurger {
		$services = $this->getServiceContainer();

		return new NeoWikiNamespacePurger(
			db: $this->getPrimaryDB(),
			wikiPageFactory: $services->getWikiPageFactory(),
			pageDeleter: new MediaWikiPageDeleter(
				deletePageFactory: $services->getDeletePageFactory(),
				performer: User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
			),
			batchSize: $this->getBatchSize()
		);
	}

	private function newSubjectSlotRemover(): SubjectSlotRemover {
		$services = $this->getServiceContainer();

		return new SubjectSlotRemover(
			db: $this->getPrimaryDB(),
			slotRoleStore: $services->getSlotRoleStore(),
			revisionStore: $services->getRevisionStore(),
			htmlCacheUpdater: $services->getHtmlCacheUpdater(),
			wanCache: $services->getMainWANObjectCache(),
			batchSize: $this->getBatchSize()
		);
	}

}

$maintClass = Uninstall::class;
require_once RUN_MAINTENANCE_IF_MAIN;
