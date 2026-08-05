<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\GraphRebuild;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use InvalidArgumentException;
use LogicException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildExecutor;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NothingToResumeException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NullRebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildAlreadyRunningException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\UnknownGraphStoreException;
use ProfessionalWiki\NeoWiki\Application\PageRebuilder;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildPhase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseRebuildStartLock;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildStartLock;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildStartLockUnavailableException;
use ProfessionalWiki\NeoWiki\Persistence\PageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\CancellingRebuildRunRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryDeletedPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\RefusingRebuildStartLock;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyRebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyRebuildJobQueue;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use TestLogger;
use Wikimedia\Rdbms\DBUnexpectedError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildExecutor
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildProgress
 * @group Database
 */
class GraphRebuildTest extends NeoWikiIntegrationTestCase {

	private const STORE = 'scoped-store';
	private const OTHER_STORE = 'other-store';
	private const WIKI_DATABASE_FAILURE_MESSAGE = 'the wiki database is gone';
	private const REQUEST_TIMEOUT_MESSAGE = 'the request ran out of time';
	private const STORE_IS_GONE_MESSAGE = 'the store is not answering';
	private const PAGE_ID_THE_WIKI_NO_LONGER_HAS = 987654;
	private const OTHER_PAGE_ID_THE_WIKI_NO_LONGER_HAS = 987655;
	private const PASSWORD = 'sekrit';
	private const CREDENTIAL_BEARING_MESSAGE =
		"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:sekrit@neo:7687')";

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

	public function testEveryPageIsProjectedIntoTheScopedStore(): void {
		$pageIds = $this->createSubjectPages( 'First page', 'Second page', 'Third page' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$this->rebuild();

		$this->assertSame( $pageIds, self::savedPageIdsFrom( $store, $pageIds[0] ) );
		$this->assertCount(
			count( $pageIds ) + self::FIXTURE_PAGES,
			$store->savedPages,
			'the Schema page behind the Subjects is projected too'
		);
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
			[ $pageIds[0], $pageIds[2], $pageIds[4] ],
			array_map( static fn ( RebuildRun $run ): int => $run->cursor, $observer->pageBatches ),
			'each batch leaves the cursor on the last page it projected'
		);
		$this->assertSame(
			[ 2, 4, 6 ],
			array_map( static fn ( RebuildRun $run ): int => $run->processed, $observer->pageBatches )
		);
	}

	public function testAPageTheStoreRejectsIsCountedAndTheRestStillProject(): void {
		$pageIds = $this->createSubjectPages( 'Fine before', 'Rejected', 'Fine after' );
		$store = new SpyGraphDatabasePlugin( refusedPageIds: [ $pageIds[1] ] );
		$this->registerStore( $store );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Succeeded, $run->status, 'one bad page does not fail the run' );
		$this->assertSame( 2 + self::FIXTURE_PAGES, $run->processed );
		$this->assertSame( 1, $run->failed );
		$this->assertSame( [ $pageIds[0], $pageIds[2] ], self::savedPageIdsFrom( $store, $pageIds[0] ) );
	}

	public function testAStoreThatCannotBePreparedFailsItsRun(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin() );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Failed, $run->status );
		$this->assertSame( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $run->error );
		$this->assertSame( 0, $run->processed, 'nothing is projected into a store that never opened' );
		$this->assertSame( 0, $run->failed, 'and no page was pushed at it to be counted against it' );
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
		$this->assertSame( $pageIds, self::savedPageIdsFrom( $workingStore, $pageIds[0] ) );
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

	public function testAPageTheStoreWillNotLetGoOfIsCountedAndTheRunStillFinishes(): void {
		$this->createSubjectPages( 'Surviving page' );
		$this->createSubjectPages( 'Page deleted during the outage' );
		$this->deletePageByName( 'Page deleted during the outage' );
		$this->registerStore( new SpyGraphDatabasePlugin( refusesDeletions: true ) );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 1, $run->failed, 'a page left in the store is a page left unreconciled' );
	}

	public function testTheRemovalsAreReportedAsTheyAreMade(): void {
		$this->createSubjectPages( 'First deleted', 'Second deleted', 'Third deleted' );
		$this->deletePageByName( 'First deleted' );
		$this->deletePageByName( 'Second deleted' );
		$this->deletePageByName( 'Third deleted' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$observer = new SpyRebuildBatchObserver();

		$this->rebuild( batchSize: 2, observer: $observer );

		$this->assertSame( [ 2, 1 ], $observer->removedInBatch, 'each batch reports what it removed' );
	}

	public function testAWikiDatabaseFailureEndsTheRunRatherThanCountingOnePage(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three' );
		$this->registerStore( $this->newStoreFailingOnTheWikiDatabase( refusedPageIds: $pageIds ) );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Failed, $run->status, 'the pages after it would fail identically' );
		$this->assertSame( self::WIKI_DATABASE_FAILURE_MESSAGE, $run->error );
		$this->assertSame( 0, $run->failed, 'the run ended rather than counting a page against the store' );
	}

	/**
	 * A request that has run out of time does not get more of it by moving to the next page, so the run
	 * ends where a wiki-database error would. The twin of the case above: both types are re-thrown by the
	 * same clause, and without this only one of them holds it in place.
	 */
	public function testARequestTimeoutEndsTheRunRatherThanCountingOnePage(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three' );
		$this->registerStore( new SpyGraphDatabasePlugin(
			refusedPageIds: $pageIds,
			failure: new TimeoutException( self::REQUEST_TIMEOUT_MESSAGE, 30.0 )
		) );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Failed, $run->status, 'the pages after it would fail identically' );
		$this->assertStringContainsString( self::REQUEST_TIMEOUT_MESSAGE, (string)$run->error );
		$this->assertSame( 0, $run->failed, 'the run ended rather than counting a page against the store' );
	}

	public function testABatchOfNothingIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( InvalidArgumentException::class );

		$this->rebuild( batchSize: 0 );
	}

	public function testRebuildingAStoreThatIsNotConfiguredIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinator();

		$this->expectException( UnknownGraphStoreException::class );

		$coordinator->rebuild( 'no-such-store', RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
	}

	public function testStartingASecondRunOfAStoreAlreadyRebuildingIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->expectException( RebuildAlreadyRunningException::class );

		$this->rebuild();
	}

	/**
	 * The rebuilder is what projects into the store, and building it can fail on its own — a backend
	 * whose configuration will not resolve, say. A run recorded before that is one nothing ran, and it
	 * blocks every later rebuild of the store, which both starting and resuming refuse while one is on.
	 */
	public function testARebuilderThatCannotBeBuiltLeavesNoRunBehind(): void {
		$coordinator = $this->newCoordinatorThatCannotBuildARebuilder();

		try {
			$coordinator->rebuild( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
			$this->fail( 'a rebuilder that cannot be built has to say so' );
		} catch ( LogicException ) {
		}

		$this->assertNull( $this->newRunRepository()->getLatestRun( self::STORE ) );
	}

	public function testARebuilderThatCannotBeBuiltLeavesTheRunItWouldHaveResumedResumable(): void {
		$this->recordFailedRunStoppedAt( cursor: 0, processed: 0 );
		$coordinator = $this->newCoordinatorThatCannotBuildARebuilder();

		try {
			$coordinator->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
			$this->fail( 'a rebuilder that cannot be built has to say so' );
		} catch ( LogicException ) {
		}

		$this->assertSame(
			RebuildStatus::Failed,
			$this->newRunRepository()->getLatestRun( self::STORE )?->status,
			'the run is left as the terminal one a later --resume can still pick up'
		);
	}

	public function testAStoreRebuildingDoesNotBlockAnotherStore(): void {
		$this->createSubjectPages( 'Page for the free store' );
		$this->registerNamedGraphDatabasePlugins( [
			self::STORE => new SpyGraphDatabasePlugin(),
			self::OTHER_STORE => new SpyGraphDatabasePlugin(),
		] );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

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

		$run = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );

		$this->assertSame(
			[ $pageIds[2], $pageIds[3] ],
			self::savedPageIds( $store ),
			'the pages the failed run already projected are not projected again'
		);
		$this->assertSame( 4, $run->processed, 'the counters keep totalling the whole rebuild' );
		$this->assertSame( RebuildStatus::Succeeded, $run->status );
	}

	/**
	 * A run interrupted while removing deleted pages must not start over at the wiki's pages: everything
	 * it already reconciled would be projected a second time before it reached where it stopped.
	 */
	public function testResumingARunInterruptedBetweenThePhasesOnlyFinishesTheRemovals(): void {
		$this->createSubjectPages( 'Surviving page' );
		$this->createSubjectPages( 'Page deleted during the outage' );
		$this->deletePageByName( 'Page deleted during the outage' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$this->recordFailedRunInTheDeletionPhase( cursor: 0 );

		$run = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( [], $store->savedPages, 'the pages the run already projected are left alone' );
		$this->assertCount( 1, $store->deletedPageIds, 'and the removals it never got to are made' );
	}

	/**
	 * The removal phase walks its own page ids under its own cursor, so a run interrupted partway through
	 * it continues after the page it got to rather than at the start of the phase.
	 */
	public function testResumingPartwayThroughTheRemovalsMakesOnlyTheOnesLeft(): void {
		$deletedPageIds = $this->createDeletedSubjectPages( 'First deleted', 'Second deleted', 'Third deleted' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$this->recordFailedRunInTheDeletionPhase( cursor: $deletedPageIds[0] );

		$run = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame(
			[ $deletedPageIds[1], $deletedPageIds[2] ],
			array_map( static fn ( PageId $pageId ): int => $pageId->id, $store->deletedPageIds ),
			'the removals the run already made are not made again'
		);
	}

	public function testResumingKeepsTheInterruptedRunAsOneRecord(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$failedRun = $this->recordFailedRunStoppedAt( $pageIds[0], processed: 1 );

		$resumedRun = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );

		$this->assertSame( $failedRun->id, $resumedRun->id );
	}

	public function testResumingAStoreWhoseLastRebuildFinishedIsRefused(): void {
		$this->createSubjectPages( 'Already reconciled page' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->rebuild();

		$this->expectException( NothingToResumeException::class );

		$this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
	}

	public function testResumingAStoreThatWasNeverRebuiltIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( NothingToResumeException::class );

		$this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
	}

	/**
	 * A page that failed is settled work: the cursor moves past it, and the next run over the wiki picks
	 * it up. Without that, a batch of nothing but failures would be read again, and again. Read a page at
	 * a time, which is the batch size at which a wholly failed batch says nothing about the store.
	 */
	public function testABatchOfNothingButFailuresIsStillWalkedPast(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three' );
		$this->registerStore( new SpyGraphDatabasePlugin( refusedPageIds: $pageIds ) );
		$observer = new SpyRebuildBatchObserver();

		$run = $this->rebuild( batchSize: 1, observer: $observer );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 3, $run->failed );
		$this->assertSame( self::FIXTURE_PAGES, $run->processed );
		$this->assertSame(
			$pageIds,
			array_slice( self::batchCursors( $observer ), -count( $pageIds ) ),
			'the walk got past every one of them'
		);
	}

	/**
	 * Every page is projected, so a page carrying no Subject is not a page there is nothing to do with:
	 * it reaches the store like any other, as its page node.
	 */
	public function testAPageWithNoSubjectIsProjectedLikeAnyOther(): void {
		$pageId = $this->getExistingTestPage( 'Page without a Subject' )->getId();
		$store = new SpyGraphDatabasePlugin();
		$observer = new SpyRebuildBatchObserver();

		$run = $this->executeOver( new InMemoryPageIdsLookup( $pageId ), $store, new NullLogger(), 200, $observer );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 1, $run->processed );
		$this->assertSame( 0, $run->failed );
		$this->assertSame( [ $pageId ], self::batchCursors( $observer ) );
		$this->assertSame( [ $pageId ], self::savedPageIds( $store ) );
	}

	/**
	 * @return int[]
	 */
	private static function batchCursors( SpyRebuildBatchObserver $observer ): array {
		return array_map( static fn ( RebuildRun $run ): int => $run->cursor, $observer->pageBatches );
	}

	/**
	 * Taking a queued run up is a write like any other a batch makes, so it too must land only while the
	 * records still have the run going. A batch reading a stale copy would otherwise write Running back
	 * over the cancellation that ended it, resurrecting a run the admin was told had stopped — and, with
	 * an automatic rebuild filed in its place, leave the store with two.
	 */
	public function testARunCancelledBeforeItsFirstBatchIsNotTakenUpAgain(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$queuedRun = $this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Api, RebuildStatus::Queued );

		$run = $this->executeOneBatchWithTheRunCancelledAsItIsRead( $queuedRun, $store, $pageIds );

		$this->assertSame( RebuildStatus::Cancelled, $run->status );
		$this->assertSame( [], $store->savedPages, 'a cancelled run projects nothing' );
		$this->assertSame(
			RebuildStatus::Cancelled,
			$this->newRunRepository()->getRun( $queuedRun->id )?->status,
			'and the record keeps the status that ended it'
		);
	}

	/**
	 * @param int[] $pageIds
	 */
	private function executeOneBatchWithTheRunCancelledAsItIsRead(
		RebuildRun $run,
		GraphDatabasePlugin $store,
		array $pageIds
	): RebuildRun {
		$executor = new GraphRebuildExecutor(
			pageIds: new InMemoryPageIdsLookup( ...$pageIds ),
			deletedPageIds: new InMemoryDeletedPageIdsLookup(),
			runs: new CancellingRebuildRunRepository( $this->newRunRepository() ),
			titleFactory: MediaWikiServices::getInstance()->getTitleFactory(),
			logger: new NullLogger(),
		);

		return $executor->executeOneBatch(
			run: $run,
			store: $store,
			pageRebuilder: NeoWikiExtension::getInstance()->newPageRebuilderFor( $store ),
			batchSize: 200,
			observer: new NullRebuildBatchObserver()
		);
	}

	/**
	 * A whole batch failing is read as the store having gone only once the store has been asked and could
	 * not be opened either.
	 */
	public function testAStoreThatFailsEveryPageOfABatchAndCannotBeOpenedFailsTheRun(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$this->registerStore( self::newStoreThatHasGone( refusedPageIds: $pageIds ) );

		$run = $this->rebuild( batchSize: 2 );

		$this->assertSame( RebuildStatus::Failed, $run->status );
		$this->assertStringContainsString( self::STORE_IS_GONE_MESSAGE, (string)$run->error );
	}

	/**
	 * A store that is up and holding a run of pages it will not take refuses a whole batch exactly as a
	 * store that has gone does, and reading that as the store having gone rewinds the cursor to the batch,
	 * so every later attempt walks back into the same pages and stops there. The pages are counted and
	 * reported instead, and the walk goes on past them.
	 */
	public function testAStoreThatStillOpensWalksPastAWholeBatchItRefused(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$store = new SpyGraphDatabasePlugin( refusedPageIds: [ $pageIds[1], $pageIds[2] ] );
		$this->registerStore( $store );

		$run = $this->rebuild( batchSize: 2 );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 2, $run->failed, 'the refused pages are counted against the wiki, not the store' );
		$this->assertSame(
			[ $pageIds[0], $pageIds[3] ],
			self::savedPageIdsFrom( $store, $pageIds[0] ),
			'the pages around the refused batch are still projected'
		);
	}

	public function testAWholeBatchAStoreRefusedWhileStillOpeningIsReportedPageByPage(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$this->registerStore( new SpyGraphDatabasePlugin( refusedPageIds: [ $pageIds[1], $pageIds[2] ] ) );
		$observer = new SpyRebuildBatchObserver();

		$this->rebuild( batchSize: 2, observer: $observer );

		$this->assertSame(
			[ $pageIds[1], $pageIds[2] ],
			$observer->failedPageIds,
			'the operator is told which pages the store would not take'
		);
	}

	/**
	 * The batch is retried rather than walked past, because the pages in it are not known to be at fault:
	 * the store was.
	 */
	public function testARunEndedByAWholeBatchFailingIsRewoundToThatBatch(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$this->registerStore( self::newStoreThatHasGone( refusedPageIds: [ $pageIds[1], $pageIds[2] ] ) );

		$run = $this->rebuild( batchSize: 2 );

		$this->assertSame( $pageIds[0], $run->cursor, 'the cursor is left where the failing batch began' );
		$this->assertSame( 1 + self::FIXTURE_PAGES, $run->processed );
		$this->assertSame( 0, $run->failed, 'the pages of the batch are not counted against the wiki' );
	}

	public function testAStoreThatFailsEveryPageOfABatchIsRetriedFromThereOnResume(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$this->registerStore( self::newStoreThatHasGone( refusedPageIds: [ $pageIds[1], $pageIds[2] ] ) );
		$this->rebuild( batchSize: 2 );

		$recoveredStore = new SpyGraphDatabasePlugin();
		$this->registerStore( $recoveredStore );
		$run = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 2, new NullRebuildBatchObserver() );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame(
			[ $pageIds[1], $pageIds[2], $pageIds[3] ],
			self::savedPageIds( $recoveredStore ),
			'the resumed run retries the rewound batch and finishes the walk'
		);
	}

	/**
	 * The last batch of a walk is a short one, so a handful of permanently unprojectable pages is enough
	 * to fail every page in it. Reading that as the store having gone would leave --resume retrying those
	 * same pages for ever.
	 */
	public function testAShortLastBatchFailingEntirelyIsNotReadAsTheStoreHavingGone(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four', 'Five' );
		// A store that would fail the liveness probe if it were asked: only the full-batch guard keeps
		// this run from being read as the store having gone.
		$this->registerStore( self::newStoreThatHasGone( refusedPageIds: [ $pageIds[3], $pageIds[4] ] ) );

		$run = $this->rebuild( batchSize: 4 );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 2, $run->failed, 'the pages of a short batch are just pages that failed' );
	}

	/**
	 * A page the walk found and the wiki has since dropped never reached the store, so letting it stand
	 * for a page the store took would walk the whole wiki one failure at a time.
	 */
	public function testAPageTheWikiDroppedDoesNotSpareAStoreThatFailedEveryPageItWasOffered(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two' );
		$store = self::newStoreThatHasGone( refusedPageIds: $pageIds );

		$run = $this->executeOver(
			new InMemoryPageIdsLookup( self::PAGE_ID_THE_WIKI_NO_LONGER_HAS, ...$pageIds ),
			$store,
			new NullLogger(),
			batchSize: 3
		);

		$this->assertSame( RebuildStatus::Failed, $run->status );
		$this->assertStringContainsString( self::STORE_IS_GONE_MESSAGE, (string)$run->error );
	}

	/**
	 * With almost every page of a batch gone from the wiki, the one page that did reach the store failing
	 * says no more about the store than a batch of one does.
	 */
	public function testABatchOnlyOnePageOfWhichReachedTheStoreIsNotReadAsTheStoreHavingGone(): void {
		$pageIds = $this->createSubjectPages( 'One' );
		$store = new SpyGraphDatabasePlugin( refusedPageIds: $pageIds );

		$run = $this->executeOver(
			new InMemoryPageIdsLookup( self::PAGE_ID_THE_WIKI_NO_LONGER_HAS, self::OTHER_PAGE_ID_THE_WIKI_NO_LONGER_HAS, ...$pageIds ),
			$store,
			new NullLogger(),
			batchSize: 3
		);

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 1, $run->failed );
	}

	/**
	 * A store that will not let go of anything is as gone as one that will not take anything, and the
	 * removal phase is where a rebuild finds that out: without this the run ends Succeeded, the store is
	 * reported in sync, and every page the wiki deleted stays queryable in it for good.
	 */
	public function testAStoreThatRefusesAWholeBatchOfRemovalsFailsTheRun(): void {
		$this->createDeletedSubjectPages( 'First deleted', 'Second deleted' );
		$this->registerStore( self::newStoreThatHasGone( refusesDeletions: true ) );

		$run = $this->rebuild( batchSize: 2 );

		$this->assertSame( RebuildStatus::Failed, $run->status );
		$this->assertSame( RebuildPhase::Deletions, $run->phase );
		$this->assertSame( 0, $run->cursor, 'the cursor is left where the failing batch began' );
		$this->assertSame( 0, $run->failed, 'the pages of the batch are not counted against the wiki' );
	}

	public function testAStoreThatRefusedAWholeBatchOfRemovalsRetriesItOnResume(): void {
		$this->createDeletedSubjectPages( 'First deleted', 'Second deleted' );
		$this->registerStore( self::newStoreThatHasGone( refusesDeletions: true ) );
		$this->rebuild( batchSize: 2 );

		$recoveredStore = new SpyGraphDatabasePlugin();
		$this->registerStore( $recoveredStore );
		$run = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 2, new NullRebuildBatchObserver() );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertCount( 2, $recoveredStore->deletedPageIds );
	}

	/**
	 * The batch is retried from where it began, so nothing may be left recording its pages as pages that
	 * failed — least of all whatever is watching the rebuild, which is where an operator reads which
	 * pages to go and look at.
	 */
	public function testThePagesOfARewoundBatchAreNotReportedAsFailed(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$this->registerStore( self::newStoreThatHasGone( refusedPageIds: [ $pageIds[1], $pageIds[2] ] ) );
		$observer = new SpyRebuildBatchObserver();

		$this->rebuild( batchSize: 2, observer: $observer );

		$this->assertSame( [], $observer->failedPageIds );
	}

	/**
	 * One page failing is one page failing however small the batch, and calling that a store failure
	 * would leave --resume retrying the same broken page for ever.
	 */
	public function testASinglePageBatchThatFailsIsJustAPageThatFailed(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin( refusedPageIds: [ $pageIds[0] ] ) );

		$run = $this->rebuild( batchSize: 1 );

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 1, $run->failed );
		$this->assertSame( 1 + self::FIXTURE_PAGES, $run->processed, 'the page after it was still reached' );
	}

	/**
	 * A store has one rebuild ahead of it whether or not anything has begun working on it yet.
	 */
	public function testStartingARunOfAStoreWithOneAlreadyQueuedIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Api, RebuildStatus::Queued );

		$this->expectException( RebuildAlreadyRunningException::class );

		$this->rebuild();
	}

	/**
	 * The check for an active run and the row it guards are taken together under the store's start lock,
	 * so a start that cannot take that lock is one that never decided anything and must leave no trace.
	 */
	public function testARebuildThatCannotTakeTheStartLockFilesNoRun(): void {
		$coordinator = $this->newCoordinatorThatCannotTakeTheStartLock();

		try {
			$coordinator->rebuild( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
			$this->fail( 'a rebuild that cannot take the start lock has to say so' );
		} catch ( RebuildStartLockUnavailableException ) {
		}

		$this->assertNull( $this->newRunRepository()->getLatestRun( self::STORE ) );
	}

	public function testAResumeThatCannotTakeTheStartLockLeavesTheRunResumable(): void {
		$this->recordFailedRunStoppedAt( cursor: 0, processed: 0 );
		$coordinator = $this->newCoordinatorThatCannotTakeTheStartLock();

		try {
			$coordinator->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
			$this->fail( 'a resume that cannot take the start lock has to say so' );
		} catch ( RebuildStartLockUnavailableException ) {
		}

		$this->assertSame( RebuildStatus::Failed, $this->newRunRepository()->getLatestRun( self::STORE )?->status );
	}

	public function testResumingAStoreThatIsNotConfiguredIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( UnknownGraphStoreException::class );

		$this->newCoordinator()->resume( 'no-such-store', RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
	}

	public function testResumingAStoreAlreadyRebuildingIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->expectException( RebuildAlreadyRunningException::class );

		$this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
	}

	public function testAWikiDatabaseFailureWhileRemovingEndsTheRunRatherThanCountingOnePage(): void {
		$this->createSubjectPages( 'Page deleted during the outage' );
		$this->deletePageByName( 'Page deleted during the outage' );
		$this->registerStore( new SpyGraphDatabasePlugin(
			refusesDeletions: true,
			failure: new DBUnexpectedError( null, self::WIKI_DATABASE_FAILURE_MESSAGE )
		) );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Failed, $run->status, 'the removals after it would fail identically' );
		$this->assertSame( self::WIKI_DATABASE_FAILURE_MESSAGE, $run->error );
		$this->assertSame( 0, $run->failed, 'the run ended rather than counting a page against the store' );
	}

	public function testARequestTimeoutWhileRemovingEndsTheRunRatherThanCountingOnePage(): void {
		$this->createSubjectPages( 'Page deleted during the outage' );
		$this->deletePageByName( 'Page deleted during the outage' );
		$this->registerStore( new SpyGraphDatabasePlugin(
			refusesDeletions: true,
			failure: new TimeoutException( self::REQUEST_TIMEOUT_MESSAGE, 30.0 )
		) );

		$run = $this->rebuild();

		$this->assertSame( RebuildStatus::Failed, $run->status, 'the removals after it would fail identically' );
		$this->assertStringContainsString( self::REQUEST_TIMEOUT_MESSAGE, (string)$run->error );
		$this->assertSame( 0, $run->failed, 'the run ended rather than counting a page against the store' );
	}

	public function testTheRunIsRecordedWithWhatItDidAndWhatStartedIt(): void {
		$this->createSubjectPages( 'Recorded page' );
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->rebuild();
		$storedRun = $this->newRunRepository()->getLatestRun( self::STORE );

		$this->assertNotNull( $storedRun );
		$this->assertSame( RebuildStatus::Succeeded, $storedRun->status );
		$this->assertSame( RebuildTrigger::Cli, $storedRun->trigger );
		$this->assertSame( 1 + self::FIXTURE_PAGES, $storedRun->processed );
		$this->assertSame( 0, $storedRun->failed );
		$this->assertSame( RebuildPhase::Deletions, $storedRun->phase, 'a run that reconciled the wiki got to its second phase' );
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

	/**
	 * A backend client reports an unreachable server by quoting the connection URI it tried, credentials
	 * and all, and the run records outlive the run: they are read from the database long afterwards.
	 */
	public function testTheRecordedRunErrorCarriesNoCredentials(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin( self::CREDENTIAL_BEARING_MESSAGE ) );

		$this->rebuild();
		$storedRun = $this->newRunRepository()->getLatestRun( self::STORE );

		$this->assertNotNull( $storedRun?->error );
		$this->assertStringNotContainsString( self::PASSWORD, $storedRun->error );
		$this->assertStringContainsString( 'bolt://neo:7687', $storedRun->error, 'the server itself still says which one' );
	}

	/**
	 * A SPARQL store reports a refused page by quoting the endpoint URL it posted to, which carries the
	 * basic-auth credentials when it has any.
	 */
	public function testTheLoggedPageFailureCarriesNoCredentials(): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$pageIds = $this->createSubjectPages( 'Page the store rejects' );
		$this->registerStore( new SpyGraphDatabasePlugin(
			refusedPageIds: $pageIds,
			failure: new RuntimeException( self::CREDENTIAL_BEARING_MESSAGE )
		) );

		$this->rebuild();

		$this->assertStringNotContainsString( self::PASSWORD, $this->loggedText( $logger ) );
		$this->assertStringContainsString( 'bolt://neo:7687', $this->loggedText( $logger ) );
	}

	/**
	 * --resume belongs to the maintenance script, so telling someone who started the rebuild from the wiki
	 * to use it names something they cannot reach.
	 */
	public function testARebuildStartedFromTheWikiIsToldToRebuildRatherThanToResume(): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin() );

		$this->newCoordinator()->rebuild( self::STORE, RebuildTrigger::Ui, 200, new NullRebuildBatchObserver() );

		$this->assertStringContainsString( 'Special:GraphStores', $this->loggedText( $logger ) );
		$this->assertStringNotContainsString( '--resume', $this->loggedText( $logger ) );
	}

	public function testARebuildStartedFromTheShellIsToldHowToResumeIt(): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin() );

		$this->rebuild();

		$this->assertStringContainsString( '--resume', $this->loggedText( $logger ) );
	}

	private function rebuild(
		int $batchSize = 200,
		RebuildBatchObserver $observer = new NullRebuildBatchObserver()
	): RebuildRun {
		return $this->newCoordinator()->rebuild( self::STORE, RebuildTrigger::Cli, $batchSize, $observer );
	}

	/**
	 * Resuming from a shell is someone driving the rebuild by hand, whatever filed the run being picked
	 * up. Left recorded as the automatic rebuild it started life as, an operator's resumed run is one an
	 * automatic restart reads as unattended and takes away from them mid-walk.
	 */
	public function testResumingARunRecordsWhoeverIsDrivingItNow(): void {
		$this->createSubjectPages( 'One' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Auto, RebuildStatus::Failed );

		$run = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );

		$this->assertSame( RebuildTrigger::Cli, $run->trigger );
	}

	public function testAnAutoStartedRunResumedFromAShellIsLeftAloneByAnAutomaticRestart(): void {
		$this->createSubjectPages( 'One' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$repository = $this->newRunRepository();
		$repository->startRun( self::STORE, RebuildTrigger::Auto, RebuildStatus::Failed );
		$resumedRun = $this->newCoordinator()->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );
		$repository->updateRun( $resumedRun->started() );

		$this->expectException( RebuildAlreadyRunningException::class );

		$this->newCoordinator()->restartBackground( self::STORE, RebuildTrigger::Auto );
	}

	/**
	 * A store that goes during the walk: it opens for the run, then refuses what it is sent and will not
	 * open again. Both halves matter, because refusing a batch is what a store holding pages it will not
	 * take does too, and reopening it is what tells the two apart.
	 *
	 * @param int[] $refusedPageIds
	 */
	private static function newStoreThatHasGone(
		array $refusedPageIds = [],
		bool $refusesDeletions = false
	): SpyGraphDatabasePlugin {
		return new SpyGraphDatabasePlugin(
			refusedPageIds: $refusedPageIds,
			refusesDeletions: $refusesDeletions,
			whenReopened: new RuntimeException( self::STORE_IS_GONE_MESSAGE )
		);
	}

	/**
	 * The plugins reach the rebuild through the real registration hook, so the tests drive the wiring an
	 * extension's backend goes through rather than a coordinator assembled by hand.
	 */
	private function registerStore( GraphDatabasePlugin $store ): void {
		$this->registerNamedGraphDatabasePlugins( [ self::STORE => $store ] );
	}

	private function newCoordinator(): GraphRebuildCoordinator {
		return NeoWikiExtension::getInstance()
			->newGraphRebuildCoordinator( GraphRebuildCoordinator::BACKGROUND_BATCH_SIZE );
	}

	private function newRunRepository(): RebuildRunRepository {
		return NeoWikiExtension::getInstance()->newRebuildRunRepository();
	}

	/**
	 * Wired by hand, because the production factory cannot be made to fail from the outside.
	 */
	private function newCoordinatorThatCannotBuildARebuilder(): GraphRebuildCoordinator {
		return new GraphRebuildCoordinator(
			stores: [ self::STORE => new SpyGraphDatabasePlugin() ],
			runs: $this->newRunRepository(),
			startLock: $this->newStartLock(),
			executor: $this->newExecutor( new InMemoryPageIdsLookup(), new NullLogger() ),
			jobQueue: new SpyRebuildJobQueue(),
			newPageRebuilder: static fn (): PageRebuilder => throw new LogicException( 'unresolvable backend' ),
			logger: new NullLogger(),
		);
	}

	private function newStartLock(): RebuildStartLock {
		return new DatabaseRebuildStartLock( MediaWikiServices::getInstance()->getConnectionProvider() );
	}

	/**
	 * Wired by hand, because a lock another process is holding cannot be produced from inside one test.
	 */
	private function newCoordinatorThatCannotTakeTheStartLock(): GraphRebuildCoordinator {
		return new GraphRebuildCoordinator(
			stores: [ self::STORE => new SpyGraphDatabasePlugin() ],
			runs: $this->newRunRepository(),
			startLock: new RefusingRebuildStartLock(),
			executor: $this->newExecutor( new InMemoryPageIdsLookup(), new NullLogger() ),
			jobQueue: new SpyRebuildJobQueue(),
			newPageRebuilder: static fn ( GraphDatabasePlugin $store ): PageRebuilder
				=> NeoWikiExtension::getInstance()->newPageRebuilderFor( $store ),
			logger: new NullLogger(),
		);
	}

	/**
	 * A page can become unreadable between the walk finding it and the rebuild reaching it — its
	 * revision vanished, or its slot content will not parse. Nothing was projected and nothing failed:
	 * the walk records why and gets past it, and the page never counts as one the store was offered,
	 * so a batch of them says nothing about the store being alive.
	 */
	public function testAPageTheRebuilderCannotReadIsSkippedAndWalkedPast(): void {
		$pageId = $this->getExistingTestPage( 'Unreadable page' )->getId();
		$store = new SpyGraphDatabasePlugin();
		$logger = new TestLogger( true );

		$rebuilder = $this->createStub( PageRebuilder::class );
		$rebuilder->method( 'rebuild' )->willReturn( PageRefreshOutcome::SkippedMissingRevision );

		$run = $this->newExecutor( new InMemoryPageIdsLookup( $pageId ), $logger )->execute(
			run: $this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running ),
			store: $store,
			pageRebuilder: $rebuilder,
			batchSize: 200,
			observer: new NullRebuildBatchObserver()
		);

		$this->assertSame( RebuildStatus::Succeeded, $run->status );
		$this->assertSame( 0, $run->processed );
		$this->assertSame( 0, $run->failed, 'a page with nothing readable to project has not failed' );
		$this->assertSame( RebuildPhase::Deletions, $run->phase, 'the walk got past it to the second phase' );
		$this->assertSame( [], $store->savedPages );
		$this->assertStringContainsString(
			PageRefreshOutcome::SkippedMissingRevision->skipReason(),
			$this->loggedText( $logger ),
			'the operator is told why the page was skipped'
		);
	}

	/**
	 * Rebuilds $store over exactly the pages $pageIds walks, rather than over what the wiki holds.
	 */
	private function executeOver(
		PageIdsLookup $pageIds,
		GraphDatabasePlugin $store,
		LoggerInterface $logger,
		int $batchSize,
		RebuildBatchObserver $observer = new NullRebuildBatchObserver()
	): RebuildRun {
		return $this->newExecutor( $pageIds, $logger )->execute(
			run: $this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running ),
			store: $store,
			pageRebuilder: NeoWikiExtension::getInstance()->newPageRebuilderFor( $store ),
			batchSize: $batchSize,
			observer: $observer
		);
	}

	private function newExecutor( PageIdsLookup $pageIds, LoggerInterface $logger ): GraphRebuildExecutor {
		return new GraphRebuildExecutor(
			pageIds: $pageIds,
			deletedPageIds: new InMemoryDeletedPageIdsLookup(),
			runs: $this->newRunRepository(),
			titleFactory: MediaWikiServices::getInstance()->getTitleFactory(),
			logger: $logger,
		);
	}

	/**
	 * A run that got through the wiki's pages and stopped while removing the ones MediaWiki no longer has,
	 * having removed everything up to and including $cursor.
	 */
	private function recordFailedRunInTheDeletionPhase( int $cursor ): RebuildRun {
		$repository = $this->newRunRepository();

		$failedRun = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )
			->enteredDeletionPhase()
			->withProgress( cursor: $cursor, processed: 1, failed: 0 )
			->failed( 'the store went away' );

		$repository->updateRun( $failedRun );

		return $failedRun;
	}

	private function recordFailedRunStoppedAt( int $cursor, int $processed ): RebuildRun {
		$repository = $this->newRunRepository();

		$failedRun = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )
			->withProgress( cursor: $cursor, processed: $processed, failed: 0 )
			->failed( 'the store went away' );

		$repository->updateRun( $failedRun );

		return $failedRun;
	}

	/**
	 * Pages that carried a Subject and that MediaWiki no longer has, which is what the removal phase of a
	 * rebuild walks.
	 *
	 * @return int[]
	 */
	private function createDeletedSubjectPages( string ...$pageNames ): array {
		$pageIds = $this->createSubjectPages( ...$pageNames );

		foreach ( $pageNames as $pageName ) {
			$this->deletePageByName( $pageName );
		}

		return $pageIds;
	}

	/**
	 * Stands in for a backend whose projection hits the wiki's own database — a page property provider
	 * reading from it, say — at the moment that database gives out. Every page after this one would fail
	 * the same way, which is what separates it from a page the store merely rejects.
	 *
	 * @param int[] $refusedPageIds
	 */
	private function newStoreFailingOnTheWikiDatabase( array $refusedPageIds ): SpyGraphDatabasePlugin {
		return new SpyGraphDatabasePlugin(
			refusedPageIds: $refusedPageIds,
			failure: new DBUnexpectedError( null, self::WIKI_DATABASE_FAILURE_MESSAGE )
		);
	}

}
