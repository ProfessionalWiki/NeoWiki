<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
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

		$reconciled = $this->runRebuild( [ '--store=typo' ] );

		$this->assertFalse( $reconciled );
		$this->assertStringContainsString( 'Unknown graph store "typo"', $this->getScriptOutput() );
	}

	public function testAStoreThatCannotBeReachedExitsNonZero(): void {
		$this->createPageWithSubjects( 'Page nobody projects', TestSubject::build() );
		$this->registerNamedGraphDatabasePlugins( [ 'broken' => new ThrowingGraphDatabasePlugin() ] );

		$reconciled = $this->runRebuild( [ '--store=broken' ] );

		$this->assertFalse( $reconciled );
		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $this->getScriptOutput() );
		$this->assertStringContainsString(
			'--resume', $this->getScriptOutput(), 'a failed run must say how to continue it'
		);
	}

	public function testOneStoreFailingDoesNotStopTheStoresAfterIt(): void {
		$this->createPageWithSubjects( 'Page the working store wants', TestSubject::build() );
		$workingStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [
			'broken' => new ThrowingGraphDatabasePlugin(),
			'working' => $workingStore,
		] );

		$reconciled = $this->runRebuild();

		$this->assertFalse( $reconciled );
		$this->assertCount( 1, $workingStore->savedPages );
	}

	public function testAPageTheStoreRejectsExitsNonZero(): void {
		$pageId = $this->createPageWithSubjects( 'Rejected page', TestSubject::build() )?->getPageId();
		$this->registerNamedGraphDatabasePlugins( [
			'picky' => new SpyGraphDatabasePlugin( refusedPageIds: [ (int)$pageId ] ),
		] );

		$reconciled = $this->runRebuild( [ '--store=picky' ] );

		$this->assertFalse( $reconciled );
		$this->assertStringContainsString( 'Projected 0 pages, 1 failed.', $this->getScriptOutput() );
	}

	public function testProgressIsReportedPerBatchRatherThanPerPage(): void {
		$this->createPageWithSubjects( 'One', TestSubject::build() );
		$this->createPageWithSubjects( 'Two', TestSubject::build() );
		$this->createPageWithSubjects( 'Three', TestSubject::build() );
		$this->registerNamedGraphDatabasePlugins( [ 'batched' => new SpyGraphDatabasePlugin() ] );

		$this->runRebuild( [ '--store=batched', '--batch-size=2' ] );

		$this->assertStringContainsString( 'batched: 2/3 pages (failed 0)', $this->getScriptOutput() );
		$this->assertStringContainsString( 'batched: 3/3 pages (failed 0)', $this->getScriptOutput() );
	}

	/**
	 * Rounding one of these up to the smallest batch that works would rebuild the whole wiki one page
	 * at a time under an option the operator got wrong, and say nothing about it.
	 *
	 * @dataProvider nonsensicalBatchSizeProvider
	 */
	public function testANonsensicalBatchSizeIsRefusedRatherThanRounded( string $batchSize ): void {
		$this->createPageWithSubjects( 'Page nobody gets to', TestSubject::build() );
		$store = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'batched' => $store ] );

		$reconciled = $this->runRebuild( [ '--batch-size=' . $batchSize ] );

		$this->assertFalse( $reconciled );
		$this->assertStringContainsString( '--batch-size', $this->getScriptOutput() );
		$this->assertSame( [], $store->savedPages, 'nothing is rebuilt under an option that makes no sense' );
	}

	public function nonsensicalBatchSizeProvider(): iterable {
		yield 'nothing at all' => [ '0' ];
		yield 'less than nothing' => [ '-5' ];
		yield 'a fraction of a page' => [ '2.5' ];
		yield 'not a number' => [ 'lots' ];
	}

	public function testResumeContinuesTheStoresUnfinishedRebuild(): void {
		$this->createPageWithSubjects( 'Page before the outage', TestSubject::build() );
		$this->createPageWithSubjects( 'Page after the outage', TestSubject::build() );
		$store = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'recovering' => new ThrowingGraphDatabasePlugin() ] );
		$this->runRebuild( [ '--store=recovering' ] );

		// The store is back: the same name now resolves to a plugin that works.
		$this->registerNamedGraphDatabasePlugins( [ 'recovering' => $store ] );
		$this->runRebuild( [ '--store=recovering', '--resume' ] );

		$this->assertCount( 2, $store->savedPages, 'the resumed run reconciles the pages the failed one did not' );
	}

	public function testResumingANamedStoreWithNothingToResumeExitsNonZero(): void {
		$this->registerNamedGraphDatabasePlugins( [ 'fresh' => new SpyGraphDatabasePlugin() ] );

		$reconciled = $this->runRebuild( [ '--store=fresh', '--resume' ] );

		$this->assertFalse( $reconciled, 'the operator asked for something that could not be done' );
		$this->assertStringContainsString( 'no unfinished rebuild to resume', $this->getScriptOutput() );
	}

	public function testResumingEveryStorePassesOverTheOnesWithNothingToResume(): void {
		$this->createPageWithSubjects( 'Page for the recovering store', TestSubject::build() );
		$recoveringStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [
			'finished' => new SpyGraphDatabasePlugin(),
			'recovering' => new ThrowingGraphDatabasePlugin(),
		] );
		$this->runRebuild();

		$this->registerNamedGraphDatabasePlugins( [
			'finished' => new SpyGraphDatabasePlugin(),
			'recovering' => $recoveringStore,
		] );
		$reconciled = $this->runRebuild( [ '--resume' ] );

		$this->assertTrue( $reconciled, 'a store whose last rebuild finished is not a failure to resume' );
		$this->assertCount( 1, $recoveringStore->savedPages );
	}

	/**
	 * A store added to the configuration since the last rebuild holds none of the wiki, and has no
	 * unfinished run to continue either. Reporting that as in sync is how a scheduled `--resume` leaves
	 * a store empty and still says it is done.
	 */
	public function testResumingEveryStoreReportsAStoreThatWasNeverRebuilt(): void {
		$this->registerNamedGraphDatabasePlugins( [ 'newly-added' => new SpyGraphDatabasePlugin() ] );

		$reconciled = $this->runRebuild( [ '--resume' ] );

		$this->assertFalse( $reconciled );
		$this->assertStringContainsString( 'newly-added', $this->getScriptOutput() );
	}

	public function testResumingEveryStoreReportsAStoreWhoseLastRunLeftPagesBehind(): void {
		$pageId = $this->createPageWithSubjects( 'Rejected page', TestSubject::build() )?->getPageId();
		$this->registerNamedGraphDatabasePlugins( [
			'picky' => new SpyGraphDatabasePlugin( refusedPageIds: [ (int)$pageId ] ),
		] );
		$this->runRebuild();

		$reconciled = $this->runRebuild( [ '--resume' ] );

		$this->assertFalse( $reconciled, 'a run that finished without reconciling every page is not in sync' );
	}

	public function testRebuildingWithNoStoresConfiguredSucceeds(): void {
		$reconciled = $this->runWithoutGraphBackend( function (): bool {
			// Replaces the registration hook, so the bundled test extension contributes no store either.
			$this->registerNamedGraphDatabasePlugins( [] );

			return $this->runRebuild();
		} );

		$this->assertTrue( $reconciled );
		$this->assertStringContainsString( 'No graph stores are configured', $this->getScriptOutput() );
	}

	/**
	 * Drives the script the way the command line does, so the run covers option parsing too. It reports
	 * failure by returning false, which is what MaintenanceRunner turns into the exit status.
	 *
	 * @param string[] $arguments
	 */
	private function runRebuild( array $arguments = [] ): bool {
		$script = new RebuildGraphDatabases();
		$script->loadWithArgv( $arguments );

		ob_start();
		try {
			$reconciled = $script->execute();
		} finally {
			$script->cleanupChanneled();
			$this->scriptOutput = (string)ob_get_clean();
		}

		return $reconciled;
	}

	private function getScriptOutput(): string {
		return $this->scriptOutput;
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
