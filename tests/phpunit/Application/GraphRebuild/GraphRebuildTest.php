<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\GraphRebuild;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NothingToResumeException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NullRebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildAlreadyRunningException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\UnknownGraphStoreException;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivelyFailingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyRebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildExecutor
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildProgress
 * @group Database
 */
class GraphRebuildTest extends NeoWikiIntegrationTestCase {

	private const STORE = 'scoped-store';
	private const OTHER_STORE = 'other-store';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The tests rebuild the singleton with test plugins registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testEveryPageCarryingASubjectIsProjectedIntoTheScopedStore(): void {
		$pageIds = $this->createSubjectPages( 'First page', 'Second page', 'Third page' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$this->rebuild();

		$this->assertSame( $pageIds, self::savedPageIds( $store ) );
	}

	public function testTheScopedStoreIsPreparedBeforeAnythingIsProjected(): void {
		$this->createSubjectPages( 'Page to project' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$this->rebuild();

		$this->assertSame( 1, $store->initializeCount );
	}

	public function testAStoreOutsideTheRunIsLeftAlone(): void {
		$this->createSubjectPages( 'Page in both stores' );
		$otherStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [
			self::STORE => new SpyGraphDatabasePlugin(),
			self::OTHER_STORE => $otherStore,
		] );

		$this->rebuild();

		$this->assertSame( [], $otherStore->savedPages, 'only the scoped store is projected into' );
		$this->assertSame( 0, $otherStore->initializeCount, 'and only the scoped store is prepared' );
	}

	public function testTheCursorAdvancesOneBatchAtATime(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four', 'Five' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$observer = new SpyRebuildBatchObserver();

		$this->rebuild( batchSize: 2, observer: $observer );

		$this->assertSame(
			[ $pageIds[1], $pageIds[3], $pageIds[4] ],
			array_map( static fn ( RebuildRun $run ): int => $run->cursor, $observer->pageBatches ),
			'each batch leaves the cursor on the last page it projected'
		);
		$this->assertSame(
			[ 2, 4, 5 ],
			array_map( static fn ( RebuildRun $run ): int => $run->processed, $observer->pageBatches )
		);
	}

	public function testProgressIsReportedAgainstTheNumberOfSubjectPages(): void {
		$this->createSubjectPages( 'One', 'Two', 'Three' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$observer = new SpyRebuildBatchObserver();

		$this->rebuild( batchSize: 2, observer: $observer );

		$this->assertSame( [ 3, 3 ], $observer->reportedPageTotals );
	}

	public function testAPageTheStoreRejectsIsCountedAndTheRestStillProject(): void {
		$pageIds = $this->createSubjectPages( 'Fine before', 'Rejected', 'Fine after' );
		$store = new SelectivelyFailingGraphDatabasePlugin( $pageIds[1] );
		$this->registerStore( $store );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Succeeded, $run->status, 'one bad page does not fail the run' );
		$this->assertSame( 2, $run->processed );
		$this->assertSame( 1, $run->failed );
		$this->assertSame( [ $pageIds[0], $pageIds[2] ], self::savedPageIds( $store ) );
	}

	public function testAStoreThatCannotBePreparedFailsItsRun(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin() );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Failed, $run->status );
		$this->assertSame( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $run->error );
		$this->assertSame( 0, $run->processed, 'nothing is projected into a store that never opened' );
	}

	public function testAStoreFailingLeavesTheNextStoresRunUntouched(): void {
		$pageIds = $this->createSubjectPages( 'Page both stores want' );
		$workingStore = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [
			self::STORE => new ThrowingGraphDatabasePlugin(),
			self::OTHER_STORE => $workingStore,
		] );
		$coordinator = $this->newCoordinator();

		$failedRun = $coordinator->rebuild( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
		$succeededRun = $coordinator->rebuild(
			self::OTHER_STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver()
		);

		$this->assertSame( RebuildStatus::Failed, $failedRun->status );
		$this->assertSame( RebuildStatus::Succeeded, $succeededRun->status );
		$this->assertSame( $pageIds, self::savedPageIds( $workingStore ) );
	}

	public function testPagesMediaWikiNoLongerHasAreRemovedFromTheScopedStore(): void {
		$this->createSubjectPages( 'Surviving page' );
		$deletedPageId = $this->createSubjectPages( 'Page deleted during the outage' )[0];
		$this->deletePageByName( 'Page deleted during the outage' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$this->rebuild();

		$this->assertSame(
			[ $deletedPageId ],
			array_map( static fn ( PageId $pageId ): int => $pageId->id, $store->deletedPageIds )
		);
	}

	public function testRebuildingAStoreThatIsNotConfiguredIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinator();

		$this->expectException( UnknownGraphStoreException::class );

		$coordinator->rebuild( 'no-such-store', RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
	}

	public function testStartingASecondRunOfAStoreAlreadyRebuildingIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli );

		$this->expectException( RebuildAlreadyRunningException::class );

		$this->rebuild();
	}

	public function testAStoreRebuildingDoesNotBlockAnotherStore(): void {
		$this->createSubjectPages( 'Page for the free store' );
		$this->registerNamedGraphDatabasePlugins( [
			self::STORE => new SpyGraphDatabasePlugin(),
			self::OTHER_STORE => new SpyGraphDatabasePlugin(),
		] );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli );

		$run = $this->newCoordinator()->rebuild(
			self::OTHER_STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver()
		);

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
	}

	public function testResumingProjectsOnlyThePagesTheFailedRunNeverReached(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$this->recordFailedRunStoppedAt( $pageIds[1], processed: 2 );

		$run = $this->newCoordinator()->resume( self::STORE, 200, new NullRebuildBatchObserver() );

		$this->assertSame(
			[ $pageIds[2], $pageIds[3] ],
			self::savedPageIds( $store ),
			'the pages the failed run already projected are not projected again'
		);
		$this->assertSame( 4, $run->processed, 'the counters keep totalling the whole rebuild' );
		$this->assertSame( RebuildStatus::Succeeded, $run->status );
	}

	public function testResumingKeepsTheInterruptedRunAsOneRecord(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$failedRun = $this->recordFailedRunStoppedAt( $pageIds[0], processed: 1 );

		$resumedRun = $this->newCoordinator()->resume( self::STORE, 200, new NullRebuildBatchObserver() );

		$this->assertSame( $failedRun->id, $resumedRun->id );
		$this->assertNull( $resumedRun->error, 'the error that ended it no longer describes the run' );
	}

	public function testResumingAStoreWhoseLastRebuildFinishedIsRefused(): void {
		$this->createSubjectPages( 'Already reconciled page' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->rebuild();

		$this->expectException( NothingToResumeException::class );

		$this->newCoordinator()->resume( self::STORE, 200, new NullRebuildBatchObserver() );
	}

	public function testResumingAStoreThatWasNeverRebuiltIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( NothingToResumeException::class );

		$this->newCoordinator()->resume( self::STORE, 200, new NullRebuildBatchObserver() );
	}

	public function testTheRunIsRecordedWithWhatItDidAndWhatStartedIt(): void {
		$pageIds = $this->createSubjectPages( 'Recorded page' );
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->rebuild();
		$storedRun = $this->newRunRepository()->getLatestRun( self::STORE );

		$this->assertNotNull( $storedRun );
		$this->assertSame( RebuildStatus::Succeeded, $storedRun->status );
		$this->assertSame( RebuildTrigger::Cli, $storedRun->trigger );
		$this->assertSame( 1, $storedRun->processed );
		$this->assertSame( 0, $storedRun->failed );
		$this->assertSame( $pageIds[0], $storedRun->cursor );
	}

	public function testAFailedRunIsRecordedSoItCanBeResumed(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin() );

		$this->rebuild();
		$storedRun = $this->newRunRepository()->getLatestRun( self::STORE );

		$this->assertNotNull( $storedRun );
		$this->assertSame( RebuildStatus::Failed, $storedRun->status );
		$this->assertSame( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $storedRun->error );
	}

	public function testARunThatFinishedIsNoLongerActive(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->rebuild();

		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
	}

	private function rebuild(
		int $batchSize = 200,
		RebuildBatchObserver $observer = new NullRebuildBatchObserver()
	): RebuildRun {
		return $this->newCoordinator()->rebuild( self::STORE, RebuildTrigger::Cli, $batchSize, $observer );
	}

	/**
	 * The plugins reach the rebuild through the real registration hook, so the tests drive the wiring an
	 * extension's backend goes through rather than a coordinator assembled by hand.
	 */
	private function registerStore( GraphDatabasePlugin $store ): void {
		$this->registerNamedGraphDatabasePlugins( [ self::STORE => $store ] );
	}

	private function newCoordinator(): GraphRebuildCoordinator {
		return NeoWikiExtension::getInstance()->newGraphRebuildCoordinator();
	}

	private function newRunRepository(): RebuildRunRepository {
		return NeoWikiExtension::getInstance()->newRebuildRunRepository();
	}

	private function recordFailedRunStoppedAt( int $cursor, int $processed ): RebuildRun {
		$repository = $this->newRunRepository();

		$failedRun = $repository->startRun( self::STORE, RebuildTrigger::Cli )
			->withProgress( cursor: $cursor, processed: $processed, failed: 0 )
			->failed( 'the store went away' );

		$repository->updateRun( $failedRun );

		return $failedRun;
	}

	/**
	 * @return int[]
	 */
	private function createSubjectPages( string ...$pageNames ): array {
		$pageIds = [];

		foreach ( $pageNames as $pageName ) {
			$revision = $this->createPageWithSubjects( $pageName, TestSubject::build() );
			$this->assertNotNull( $revision );
			$pageIds[] = $revision->getPageId();
		}

		return $pageIds;
	}

	/**
	 * @return int[]
	 */
	private static function savedPageIds( SpyGraphDatabasePlugin|SelectivelyFailingGraphDatabasePlugin $store ): array {
		return array_map( static fn ( Page $page ): int => $page->getId()->id, $store->savedPages );
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
