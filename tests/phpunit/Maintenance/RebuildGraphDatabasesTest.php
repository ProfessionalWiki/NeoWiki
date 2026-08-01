<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivelyFailingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/RebuildGraphDatabases.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases
 * @group Database
 */
class RebuildGraphDatabasesTest extends NeoWikiIntegrationTestCase {

	private string $scriptOutput = '';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The test rebuilds the singleton with a spy plugin registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testRebuildSucceedsOnAWikiThatHasNeverStoredASubject(): void {
		// A wiki with no Subjects has never registered the 'neo' slot role, so the role-id lookup
		// throws NameTableAccessException. Forcing that state (empty table + a store without a warmed
		// cache) proves the rebuild treats it as an empty run instead of crashing.
		$this->truncateTable( 'slot_roles' );
		$this->getServiceContainer()->resetServiceForTesting( 'SlotRoleStore' );

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild();

		$this->assertSame( [], $spy->savedPages, 'a wiki with no Subjects has nothing to project' );
	}

	public function testRebuildRemovesADeletedSubjectPageFromTheGraph(): void {
		$this->createPageWithSubjects( 'Surviving page before', TestSubject::build() );
		$deleted = $this->createPageWithSubjects( 'Deleted during outage', TestSubject::build() );
		$this->createPageWithSubjects( 'Surviving page after', TestSubject::build() );

		$this->deletePageByName( 'Deleted during outage' );

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild();

		$this->assertSame(
			[ $deleted->getPageId() ],
			array_map( static fn ( PageId $pageId ) => $pageId->id, $spy->deletedPageIds ),
			'the rebuild should remove exactly the page MediaWiki no longer has'
		);
	}

	/**
	 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore::initialize
	 */
	public function testRebuildCreatesGraphUniquenessConstraints(): void {
		// setUpNeo4j() dropped any pre-existing constraints, so the graph starts without them. A subject
		// page is present so the rebuild re-projects real data under the freshly created constraints. This
		// is the end-to-end proof that the rebuild's initialization step (routed through the graph plugin
		// system) reaches Neo4j: both constraints exist by name. Their shape and enforcement are covered by
		// Neo4jConstraintUpdaterTest.
		$this->createPageWithSubjects( 'Page with subject', TestSubject::build() );

		$this->runRebuild();

		$this->assertSame(
			[
				[ 'name' => 'Page wiki_id id' ],
				[ 'name' => 'Subject id' ],
			],
			$this->readGraph( 'SHOW CONSTRAINTS YIELD name ORDER BY name' )->toRecursiveArray()
		);
	}

	/**
	 * The rebuild runs on wikis with no graph backend configured (e.g. a SPARQL-only install with no
	 * store), so its initialization step must be a no-op there rather than throwing.
	 *
	 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin::initialize
	 */
	public function testInitializingGraphDatabasesDoesNotThrowWithoutABackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$extension = NeoWikiExtension::getInstance();
			$this->assertNull( $extension->getNeo4jPlugin(), 'precondition: no Neo4j backend is configured' );

			// The rebuild's initialization step, resolved through the facade exactly as the script does.
			// Must not throw despite there being no store to initialize.
			$extension->getGraphDatabasePlugin()->initialize();
		} );
	}

	public function testWithoutAStoreOptionEveryConfiguredStoreIsRebuilt(): void {
		$pageId = $this->createPageWithSubjects( 'Page for every store', TestSubject::build() )?->getPageId();
		$first = new SpyGraphDatabasePlugin();
		$second = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'first-store' => $first, 'second-store' => $second ] );

		$this->runRebuild();

		$this->assertCount( 1, $first->savedPages );
		$this->assertCount( 1, $second->savedPages );
		$this->assertSame( $pageId, $second->savedPages[0]->getId()->id );
	}

	public function testTheStoreOptionRebuildsOnlyThatStore(): void {
		$this->createPageWithSubjects( 'Page for one store', TestSubject::build() );
		$scopedStore = new SpyGraphDatabasePlugin();
		$otherStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'scoped' => $scopedStore, 'other' => $otherStore ] );

		$this->runRebuild( [ '--store=scoped' ] );

		$this->assertCount( 1, $scopedStore->savedPages );
		$this->assertSame( [], $otherStore->savedPages );
	}

	public function testRebuildingAnUnconfiguredStoreExitsNonZero(): void {
		$this->registerNamedGraphDatabasePlugins( [ 'scoped' => new SpyGraphDatabasePlugin() ] );

		$output = $this->runRebuildExpectingNonZeroExit( [ '--store=typo' ] );

		$this->assertStringContainsString( 'Unknown graph store "typo"', $output );
	}

	public function testAStoreThatCannotBeReachedExitsNonZero(): void {
		$this->createPageWithSubjects( 'Page nobody projects', TestSubject::build() );
		$this->registerNamedGraphDatabasePlugins( [ 'broken' => new ThrowingGraphDatabasePlugin() ] );

		$output = $this->runRebuildExpectingNonZeroExit( [ '--store=broken' ] );

		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $output );
		$this->assertStringContainsString( '--resume', $output, 'a failed run must say how to continue it' );
	}

	public function testOneStoreFailingDoesNotStopTheStoresAfterIt(): void {
		$this->createPageWithSubjects( 'Page the working store wants', TestSubject::build() );
		$workingStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [
			'broken' => new ThrowingGraphDatabasePlugin(),
			'working' => $workingStore,
		] );

		$this->runRebuildExpectingNonZeroExit();

		$this->assertCount( 1, $workingStore->savedPages );
	}

	public function testAPageTheStoreRejectsExitsNonZero(): void {
		$pageId = $this->createPageWithSubjects( 'Rejected page', TestSubject::build() )?->getPageId();
		$this->registerNamedGraphDatabasePlugins( [
			'picky' => new SelectivelyFailingGraphDatabasePlugin( (int)$pageId ),
		] );

		$output = $this->runRebuildExpectingNonZeroExit( [ '--store=picky' ] );

		$this->assertStringContainsString( '1 pages failed', $output );
	}

	public function testProgressIsReportedPerBatchRatherThanPerPage(): void {
		$this->createPageWithSubjects( 'One', TestSubject::build() );
		$this->createPageWithSubjects( 'Two', TestSubject::build() );
		$this->createPageWithSubjects( 'Three', TestSubject::build() );
		$this->registerNamedGraphDatabasePlugins( [ 'batched' => new SpyGraphDatabasePlugin() ] );

		$output = $this->runRebuild( [ '--store=batched', '--batch-size=2' ] );

		$this->assertStringContainsString( 'batched: 2/3 pages (failed 0)', $output );
		$this->assertStringContainsString( 'batched: 3/3 pages (failed 0)', $output );
	}

	public function testResumeContinuesTheStoresUnfinishedRebuild(): void {
		$this->createPageWithSubjects( 'Page before the outage', TestSubject::build() );
		$this->createPageWithSubjects( 'Page after the outage', TestSubject::build() );
		$store = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'recovering' => new ThrowingGraphDatabasePlugin() ] );
		$this->runRebuildExpectingNonZeroExit( [ '--store=recovering' ] );

		// The store is back: the same name now resolves to a plugin that works.
		$this->registerNamedGraphDatabasePlugins( [ 'recovering' => $store ] );
		$this->runRebuild( [ '--store=recovering', '--resume' ] );

		$this->assertCount( 2, $store->savedPages, 'the resumed run reconciles the pages the failed one did not' );
	}

	public function testResumingAStoreWithNothingToResumeExitsNonZero(): void {
		$this->registerNamedGraphDatabasePlugins( [ 'fresh' => new SpyGraphDatabasePlugin() ] );

		$output = $this->runRebuildExpectingNonZeroExit( [ '--store=fresh', '--resume' ] );

		$this->assertStringContainsString( 'no unfinished rebuild to resume', $output );
	}

	/**
	 * Drives the script the way the command line does, so the run covers option parsing too.
	 *
	 * @param string[] $arguments
	 */
	private function runRebuild( array $arguments = [] ): string {
		$script = new RebuildGraphDatabases();
		$script->loadWithArgv( $arguments );

		ob_start();
		try {
			$script->execute();
		} finally {
			$script->cleanupChanneled();
			$this->scriptOutput = (string)ob_get_clean();
		}

		return $this->scriptOutput;
	}

	/**
	 * The script signals an unreconciled rebuild by exiting non-zero, which under PHPUnit surfaces as a
	 * MaintenanceFatalError instead of ending the suite. Returns what it printed before giving up.
	 *
	 * @param string[] $arguments
	 */
	private function runRebuildExpectingNonZeroExit( array $arguments = [] ): string {
		try {
			$this->runRebuild( $arguments );
		} catch ( MaintenanceFatalError ) {
			return $this->scriptOutput;
		}

		$this->fail( 'the rebuild should have exited non-zero' );
	}

	private function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
	}

}
