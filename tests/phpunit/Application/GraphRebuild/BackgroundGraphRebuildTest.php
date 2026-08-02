<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\GraphRebuild;

use Closure;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NothingToCancelException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\NullRebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildAlreadyRunningException;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\UnknownGraphStoreException;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\CancellingRebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyRebuildJobQueue;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use TestLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\GraphRebuildJob
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\MediaWikiRebuildJobQueue
 * @group Database
 */
class BackgroundGraphRebuildTest extends NeoWikiIntegrationTestCase {

	private const STORE = 'background-store';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testAStartedRebuildIsQueuedAndHasProjectedNothingYet(): void {
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$run = $this->newCoordinator()->startBackground( self::STORE, RebuildTrigger::Api );

		$this->assertSame( RebuildStatus::Queued, $run->status );
		$this->assertSame( RebuildTrigger::Api, $run->trigger );
		$this->assertSame( [], $store->savedPages );
	}

	public function testWorkingThroughTheQueueProjectsTheWholeWikiOverSeveralBatches(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four', 'Five' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$run = $this->newCoordinator( batchSize: 2 )->startBackground( self::STORE, RebuildTrigger::Api );
		$this->runJobs();

		$this->assertSame( RebuildStatus::Succeeded, $this->readRun( $run )?->status );
		$this->assertSame( $pageIds, self::savedPageIds( $store ) );
	}

	public function testEachBatchQueuesTheNextOneUntilTheRunIsDone(): void {
		$this->createSubjectPages( 'One', 'Two', 'Three' );
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$run = $this->newCoordinator( batchSize: 1 )->startBackground( self::STORE, RebuildTrigger::Api );
		$this->runJobs( [ 'minJobs' => 4 ] );

		$this->assertSame( RebuildStatus::Succeeded, $this->readRun( $run )?->status );
	}

	public function testTheFirstBatchTakesTheRunFromQueuedToRunning(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinator( batchSize: 1 );

		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$coordinator->continueInBackground( $run->id, self::STORE );

		$this->assertSame( RebuildStatus::Running, $this->readRun( $run )?->status );
	}

	public function testABackgroundRebuildRemovesThePagesMediaWikiNoLongerHas(): void {
		$this->createSubjectPages( 'Page deleted during the outage' );
		$this->deletePageByName( 'Page deleted during the outage' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$this->newCoordinator()->startBackground( self::STORE, RebuildTrigger::Api );
		$this->runJobs();

		$this->assertCount( 1, $store->deletedPageIds );
	}

	public function testARebuildQueuedWhileOneIsAlreadyGoingIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$this->newCoordinator()->startBackground( self::STORE, RebuildTrigger::Api );

		$this->expectException( RebuildAlreadyRunningException::class );

		$this->newCoordinator()->startBackground( self::STORE, RebuildTrigger::Ui );
	}

	public function testARebuildOfAStoreThatIsNotConfiguredIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( UnknownGraphStoreException::class );

		$this->newCoordinator()->startBackground( 'no-such-store', RebuildTrigger::Api );
	}

	/**
	 * A queued run nothing will ever pick up blocks every later rebuild of that store, so a queue that
	 * will not take the batch has to end the run it was filed for.
	 */
	public function testARunWhoseFirstBatchCannotBeQueuedIsNotLeftBlockingTheStore(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinatorWithJobQueue( SpyRebuildJobQueue::refusingEverything() );

		try {
			$coordinator->startBackground( self::STORE, RebuildTrigger::Api );
			$this->fail( 'a rebuild whose batch cannot be queued has to say so' );
		} catch ( RuntimeException ) {
		}

		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
	}

	/**
	 * A rebuild is carried forward by each batch queueing the next, so a queue that stops taking them
	 * partway leaves the run recorded as going with nothing going, blocking the store until someone
	 * cancels it. What it projected up to there stays, and the run is resumable.
	 */
	public function testARunWhoseNextBatchCannotBeQueuedIsNotLeftBlockingTheStore(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$run = $this->newCoordinator()->startBackground( self::STORE, RebuildTrigger::Api );

		$this->newCoordinatorWithJobQueue( SpyRebuildJobQueue::refusingEverything() )
			->continueInBackground( $run->id, self::STORE );

		$this->assertSame( RebuildStatus::Failed, $this->readRun( $run )?->status );
		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
		$this->assertCount( 2, $store->savedPages, 'what the batch projected before that stays projected' );
	}

	public function testCancellingAQueuedRunEndsItBeforeAnythingIsProjected(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$coordinator = $this->newCoordinator();

		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$cancelledRun = $coordinator->cancel( self::STORE );
		$this->runJobs();

		$this->assertSame( $run->id, $cancelledRun->id );
		$this->assertSame( RebuildStatus::Cancelled, $this->readRun( $run )?->status );
		$this->assertSame( [], $store->savedPages, 'a cancelled run projects nothing when its job runs' );
	}

	/**
	 * The run record is the state, so a batch that has already begun stops at its next boundary, leaving
	 * what it projected up to there in place.
	 */
	public function testCancellingARunningRebuildStopsItAtTheNextBatch(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$coordinator = $this->newCoordinator( batchSize: 2 );

		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$coordinator->continueInBackground( $run->id, self::STORE );
		$coordinator->cancel( self::STORE );
		$this->runJobs();

		$this->assertSame( RebuildStatus::Cancelled, $this->readRun( $run )?->status );
		$this->assertSame( [ $pageIds[0], $pageIds[1] ], self::savedPageIds( $store ) );
	}

	/**
	 * A rebuild running in a maintenance script reads the same record, so it stops for a cancellation
	 * asked for from anywhere.
	 */
	public function testCancellingStopsARebuildRunningInTheForeground(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$coordinator = $this->newCoordinator();

		$run = $coordinator->rebuild(
			self::STORE,
			RebuildTrigger::Cli,
			2,
			new CancellingRebuildBatchObserver( $coordinator, self::STORE )
		);

		$this->assertSame( RebuildStatus::Cancelled, $run->status );
		$this->assertSame( [ $pageIds[0], $pageIds[1] ], self::savedPageIds( $store ) );
	}

	/**
	 * A batch reads the run, then spends minutes projecting before writing back what it got through. A
	 * cancellation landing in that window must not be written straight back over — the admin was told the
	 * rebuild had stopped.
	 */
	public function testARebuildCancelledWhileABatchWasWorkingStaysCancelled(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin( whileSavingEachPage: $this->cancelOnce() ) );
		$run = $this->newCoordinator( batchSize: 2 )->startBackground( self::STORE, RebuildTrigger::Api );

		$this->newCoordinator( batchSize: 2 )->continueInBackground( $run->id, self::STORE );

		$this->assertSame( RebuildStatus::Cancelled, $this->readRun( $run )?->status );
	}

	public function testABatchCancelledWhileItWorkedQueuesNoFurtherBatch(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin( whileSavingEachPage: $this->cancelOnce() ) );
		$jobQueue = new SpyRebuildJobQueue();
		$run = $this->newCoordinatorWithJobQueue( $jobQueue )->startBackground( self::STORE, RebuildTrigger::Api );
		$jobQueue->pushedBatches = [];

		$this->newCoordinatorWithJobQueue( $jobQueue )->continueInBackground( $run->id, self::STORE );

		$this->assertSame( [], $jobQueue->pushedBatches );
	}

	public function testEachBatchOfARunQueuesExactlyOneFurtherBatch(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$jobQueue = new SpyRebuildJobQueue();
		$coordinator = $this->newCoordinatorWithJobQueue( $jobQueue );
		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$jobQueue->pushedBatches = [];

		$coordinator->continueInBackground( $run->id, self::STORE );

		$this->assertSame( [ [ 'runId' => $run->id, 'store' => self::STORE ] ], $jobQueue->pushedBatches );
	}

	/**
	 * Each background batch runs in a process that has opened nothing, so every one of them opens the
	 * store rather than trusting a previous batch to have done it.
	 */
	public function testEveryBackgroundBatchOpensTheStore(): void {
		$this->createSubjectPages( 'One', 'Two', 'Three' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$run = $this->newCoordinator( batchSize: 1 )->startBackground( self::STORE, RebuildTrigger::Api );
		$this->newCoordinator( batchSize: 1 )->continueInBackground( $run->id, self::STORE );
		$this->newCoordinator( batchSize: 1 )->continueInBackground( $run->id, self::STORE );

		$this->assertSame( 2, $store->initializeCount );
	}

	public function testCancellingAStoreWithNoRebuildIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( NothingToCancelException::class );

		$this->newCoordinator()->cancel( self::STORE );
	}

	public function testCancellingAStoreThatIsNotConfiguredIsRefused(): void {
		$this->registerStore( new SpyGraphDatabasePlugin() );

		$this->expectException( UnknownGraphStoreException::class );

		$this->newCoordinator()->cancel( 'no-such-store' );
	}

	public function testACancelledRunIsResumable(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$coordinator = $this->newCoordinator( batchSize: 2 );

		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$coordinator->continueInBackground( $run->id, self::STORE );
		$coordinator->cancel( self::STORE );
		$coordinator->resume( self::STORE, 200, new NullRebuildBatchObserver() );

		$this->assertSame( $pageIds, self::savedPageIds( $store ) );
	}

	/**
	 * A batch that could not run at all leaves no way for the queue to carry the run forward, so it has
	 * to end the run rather than leave it recorded as going with nothing going.
	 */
	public function testABatchThatCrashesEndsTheRunRatherThanLeavingItGoing(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$this->registerStore( new ThrowingGraphDatabasePlugin() );
		$coordinator = $this->newCoordinator();

		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$this->runJobs();

		$this->assertSame( RebuildStatus::Failed, $this->readRun( $run )?->status );
		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
	}

	public function testABatchOfARunThatHasSinceEndedDoesNothing(): void {
		$this->createSubjectPages( 'Page nobody projects' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );
		$coordinator = $this->newCoordinator();
		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$coordinator->cancel( self::STORE );

		$coordinator->continueInBackground( $run->id, self::STORE );

		$this->assertSame( [], $store->savedPages );
		$this->assertSame( RebuildStatus::Cancelled, $this->readRun( $run )?->status );
	}

	/**
	 * Nothing will ever advance whatever filed such a batch, so it is worth a line: the store may be left
	 * with a run recorded as queued that only cancelling releases.
	 */
	public function testABatchOfARunNothingWasFiledUnderProjectsNothingAndSaysSo(): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$this->createSubjectPages( 'Page nobody projects' );
		$store = new SpyGraphDatabasePlugin();
		$this->registerStore( $store );

		$this->newCoordinator()->continueInBackground( 123456, self::STORE );

		$this->assertSame( [], $store->savedPages );
		$this->assertStringContainsString( 'which the records do not have', self::loggedText( $logger ) );
	}

	public function testWhyABackgroundRebuildCouldNotRunABatchIsLogged(): void {
		$logger = new TestLogger( true );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinatorWithoutTheStoreItRebuilds( $logger );
		$run = $this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Auto, RebuildStatus::Queued );

		$coordinator->continueInBackground( $run->id, self::STORE );

		$this->assertSame( RebuildStatus::Failed, $this->readRun( $run )?->status );
		$this->assertStringContainsString( 'could not run a batch', self::loggedText( $logger ) );
	}

	private static function loggedText( TestLogger $logger ): string {
		return implode( "\n", array_column( $logger->getBuffer(), 1 ) );
	}

	private function newCoordinator( int $batchSize = GraphRebuildCoordinator::BACKGROUND_BATCH_SIZE ): GraphRebuildCoordinator {
		return NeoWikiExtension::getInstance()->newGraphRebuildCoordinator( $batchSize );
	}

	/**
	 * @return Closure(): void Cancels the store's rebuild the first time it is called, standing in for
	 *         someone pressing cancel while a batch is working.
	 */
	private function cancelOnce(): Closure {
		$cancelled = false;

		return function () use ( &$cancelled ): void {
			if ( !$cancelled ) {
				$cancelled = true;
				$this->newCoordinator()->cancel( self::STORE );
			}
		};
	}

	private function newCoordinatorWithJobQueue( SpyRebuildJobQueue $jobQueue ): GraphRebuildCoordinator {
		$extension = NeoWikiExtension::getInstance();

		return new GraphRebuildCoordinator(
			stores: $extension->getNamedGraphDatabasePlugins(),
			runs: $extension->newRebuildRunRepository(),
			startLock: $extension->newRebuildStartLock(),
			executor: $extension->newGraphRebuildExecutor(),
			jobQueue: $jobQueue,
			newPageRebuilder: static fn ( GraphDatabasePlugin $store ) => $extension->newSubjectPageRebuilderFor( $store ),
			logger: new NullLogger(),
		);
	}

	/**
	 * A coordinator whose store list does not hold the store its runs are filed under, standing in for a
	 * backend that has been unconfigured since the rebuild was queued.
	 */
	private function newCoordinatorWithoutTheStoreItRebuilds( LoggerInterface $logger ): GraphRebuildCoordinator {
		$extension = NeoWikiExtension::getInstance();

		return new GraphRebuildCoordinator(
			stores: [],
			runs: $extension->newRebuildRunRepository(),
			startLock: $extension->newRebuildStartLock(),
			executor: $extension->newGraphRebuildExecutor(),
			jobQueue: new SpyRebuildJobQueue(),
			newPageRebuilder: static fn ( GraphDatabasePlugin $store ) => $extension->newSubjectPageRebuilderFor( $store ),
			logger: $logger,
		);
	}

	private function registerStore( GraphDatabasePlugin $store ): void {
		$this->registerNamedGraphDatabasePlugins( [ self::STORE => $store ] );
	}

	private function newRunRepository(): RebuildRunRepository {
		return NeoWikiExtension::getInstance()->newRebuildRunRepository();
	}

	private function readRun( RebuildRun $run ): ?RebuildRun {
		return $this->newRunRepository()->getRun( $run->id );
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
	private static function savedPageIds( SpyGraphDatabasePlugin $store ): array {
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
