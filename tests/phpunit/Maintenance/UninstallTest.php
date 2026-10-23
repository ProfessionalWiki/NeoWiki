<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use JobSpecification;
use MediaWiki\Language\RawMessage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildLastEditorPagesJob;
use ProfessionalWiki\NeoWiki\Maintenance\Uninstall;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\NeoWikiTables;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\NeoWikiNamespacePurger;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use StatusValue;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/Uninstall.php';

/**
 * Runs the script against the test wiki's database. It drops a scratch table in place of NeoWiki's own,
 * which the tests after this one need.
 *
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\Uninstall
 * @group Database
 */
class UninstallTest extends NeoWikiIntegrationTestCase {

	public const string SCRATCH_TABLE = 'neowiki_uninstall_test_scratch';
	private const string SUBJECT_PAGE = 'UninstallTestSubjectPage';

	private string $scriptOutput = '';

	protected function setUp(): void {
		parent::setUp();
		$this->createScratchTable( self::SCRATCH_TABLE );
	}

	protected function tearDown(): void {
		$this->getDb()->dropTable( self::SCRATCH_TABLE, __METHOD__ );
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testWithoutDeleteNothingIsRemoved(): void {
		$this->createPageWithSubjects( self::SUBJECT_PAGE, TestSubject::build() );

		$this->assertTrue( $this->runUninstall() );

		$this->assertSame( 1, $this->countSubjectSlots() );
		$this->assertTrue( $this->scratchTableExists() );
	}

	public function testWithoutDeleteItReportsWhatItWouldRemove(): void {
		$this->createPageWithSubjects( self::SUBJECT_PAGE, TestSubject::build() );
		$this->changeSubjectsOfPage( self::SUBJECT_PAGE, TestSubject::build( label: 'Second revision' ) );
		$this->createSchema( 'Company' );

		$this->runUninstall();

		$this->assertStringContainsString( 'Pages holding Subjects: 1', $this->scriptOutput );
		$this->assertStringContainsString( 'Pages in NeoWiki namespaces: 1', $this->scriptOutput );
		$this->assertStringContainsString( self::SCRATCH_TABLE, $this->scriptOutput );
	}

	public function testRemovesTheSubjectsFromEveryRevision(): void {
		$this->createPageWithSubjects( self::SUBJECT_PAGE, TestSubject::build() );

		$this->assertTrue( $this->runUninstall( '--delete' ) );

		$this->assertSame( 0, $this->countSubjectSlots() );
	}

	public function testRemovesEveryPageInNeoWikiNamespaces(): void {
		$this->createSchema( 'Company' );

		$this->runUninstall( '--delete' );

		$this->assertSame( 0, $this->countRows( 'page', [ 'page_namespace' => NeoWikiNamespacePurger::NAMESPACES ] ) );
		$this->assertSame( 0, $this->countRows( 'archive', [ 'ar_namespace' => NeoWikiNamespacePurger::NAMESPACES ] ) );
	}

	public function testDeletesTheQueuedNeoWikiJobsOnly(): void {
		$jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$jobQueueGroup->push( new JobSpecification( RebuildLastEditorPagesJob::TYPE, [] ) );
		$jobQueueGroup->push( new JobSpecification( 'null', [] ) );

		$this->runUninstall( '--delete' );

		$this->assertSame( 0, $jobQueueGroup->get( RebuildLastEditorPagesJob::TYPE )->getSize() );
		$this->assertSame( 1, $jobQueueGroup->get( 'null' )->getSize() );
	}

	public function testDropsTheTables(): void {
		$this->runUninstall( '--delete' );

		$this->assertFalse( $this->scratchTableExists() );
	}

	public function testClearsEveryGraphStore(): void {
		$pageId = new PageId( $this->createPageWithSubjects( self::SUBJECT_PAGE, TestSubject::build() )->getPageId() );
		$store = new SpyGraphDatabasePlugin();
		$otherStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'first' => $store, 'second' => $otherStore ] );

		$this->runUninstall( '--delete' );

		$this->assertContainsEquals( $pageId, $store->deletedPageIds );
		$this->assertContainsEquals( $pageId, $otherStore->deletedPageIds );
	}

	public function testAnUnreachableGraphStoreStopsTheRunBeforeAnythingIsRemoved(): void {
		$this->createPageWithSubjects( self::SUBJECT_PAGE, TestSubject::build() );
		$this->createSchema( 'Company' );
		$this->registerNamedGraphDatabasePlugins( [ 'offsite' => new ThrowingGraphDatabasePlugin() ] );

		$this->assertFalse( $this->runUninstall( '--delete' ) );

		$this->assertSame( 1, $this->countSubjectSlots() );
		$this->assertTrue( Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'Company' )->exists() );
		$this->assertStringContainsString( 'Could not reach graph store offsite', $this->scriptOutput );
	}

	/**
	 * A later run finds the pages it has to take out of the store in the archive.
	 */
	public function testAGraphStoreThatFailsWhileBeingClearedStopsTheRunBeforeDeletedPagesArePurged(): void {
		$this->createSchema( 'Company' );
		$this->registerNamedGraphDatabasePlugins( [ 'failing' => new SpyGraphDatabasePlugin( refusesDeletions: true ) ] );

		$this->assertFalse( $this->runUninstall( '--delete' ) );

		$this->assertGreaterThan( 0, $this->countRows( 'archive', [ 'ar_namespace' => NeoWikiExtension::NS_SCHEMA ] ) );
		$this->assertTrue( $this->scratchTableExists() );
	}

	public function testAPageThatCannotBeDeletedKeepsNeoWikiInstalled(): void {
		$this->createSchema( 'Company' );
		$this->refuseEveryDeletion( 'Kept for legal reasons' );

		$this->assertFalse( $this->runUninstall( '--delete' ) );

		$this->assertTrue( $this->scratchTableExists() );
		$this->assertStringContainsString( 'Schema:Company', $this->scriptOutput );
	}

	public function testSaysWhyAPageCouldNotBeDeleted(): void {
		$this->createSchema( 'Company' );
		$this->refuseEveryDeletion( 'Kept for legal reasons' );

		$this->runUninstall( '--delete' );

		$this->assertStringContainsString( 'Schema:Company: Kept for legal reasons', $this->scriptOutput );
	}

	public function testRefusesABatchSizeBelowOne(): void {
		$this->createPageWithSubjects( self::SUBJECT_PAGE, TestSubject::build() );

		$this->assertFalse( $this->runUninstall( '--delete', '--batch-size=0' ) );

		$this->assertSame( 1, $this->countSubjectSlots() );
	}

	public function testDeletesEveryJobTypeNeoWikiRegisters(): void {
		$this->assertEqualsCanonicalizing( array_keys( $this->readExtensionJson()['JobClasses'] ), Uninstall::JOB_TYPES );
	}

	/**
	 * Without the bundled Neo4j backend, so that the only graph stores are those a test registers.
	 */
	private function runUninstall( string ...$arguments ): bool {
		return $this->runWithoutGraphBackend( function () use ( $arguments ): bool {
			$script = new class() extends Uninstall {

				protected function newTables(): NeoWikiTables {
					return new NeoWikiTables( $this->getPrimaryDB(), [ UninstallTest::SCRATCH_TABLE ] );
				}

			};
			$script->loadWithArgv( $arguments );

			ob_start();
			try {
				return $script->execute();
			} finally {
				$script->cleanupChanneled();
				$this->scriptOutput = (string)ob_get_clean();
			}
		} );
	}

	private function refuseEveryDeletion( string $refusal ): void {
		$this->setTemporaryHook(
			'PageDelete',
			static function ( ProperPageIdentity $page, Authority $deleter, string $reason, StatusValue $status ) use (
				$refusal
			): bool {
				$status->fatal( new RawMessage( $refusal ) );
				return false;
			}
		);
	}

	private function scratchTableExists(): bool {
		return $this->getDb()->tableExists( self::SCRATCH_TABLE, __METHOD__ );
	}

}
