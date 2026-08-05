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
		$this->assertSame( $pageIds, self::savedPageIdsFrom( $store, $pageIds[0] ) );
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
		$coordinator->continueInBackground( $run->id );

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
			->continueInBackground( $run->id );

		$this->assertSame( RebuildStatus::Failed, $this->readRun( $run )?->status );
		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
		$this->assertCount(
			2 + self::FIXTURE_PAGES,
			$store->savedPages,
			'what the batch projected before that stays projected'
		);
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
		$coordinator->continueInBackground( $run->id );
		$coordinator->cancel( self::STORE );
		$this->runJobs();

		$this->assertSame( RebuildStatus::Cancelled, $this->readRun( $run )?->status );
		$this->assertSame( [ $pageIds[0] ], self::savedPageIdsFrom( $store, $pageIds[0] ) );
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
		$this->assertSame( [ $pageIds[0] ], self::savedPageIdsFrom( $store, $pageIds[0] ) );
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

		$this->newCoordinator( batchSize: 2 )->continueInBackground( $run->id );

		$this->assertSame( RebuildStatus::Cancelled, $this->readRun( $run )?->status );
	}

	public function testABatchCancelledWhileItWorkedQueuesNoFurtherBatch(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin( whileSavingEachPage: $this->cancelOnce() ) );
		$jobQueue = new SpyRebuildJobQueue();
		$run = $this->newCoordinatorWithJobQueue( $jobQueue )->startBackground( self::STORE, RebuildTrigger::Api );
		$jobQueue->pushedBatches = [];

		$this->newCoordinatorWithJobQueue( $jobQueue )->continueInBackground( $run->id );

		$this->assertSame( [], $jobQueue->pushedBatches );
	}

	public function testEachBatchOfARunQueuesExactlyOneFurtherBatch(): void {
		$this->createSubjectPages( 'One', 'Two' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$jobQueue = new SpyRebuildJobQueue();
		$coordinator = $this->newCoordinatorWithJobQueue( $jobQueue );
		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$jobQueue->pushedBatches = [];

		$coordinator->continueInBackground( $run->id );

		$this->assertSame( [ $run->id ], $jobQueue->pushedBatches );
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
		$this->newCoordinator( batchSize: 1 )->continueInBackground( $run->id );
		$this->newCoordinator( batchSize: 1 )->continueInBackground( $run->id );

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
		$coordinator->continueInBackground( $run->id );
		$coordinator->cancel( self::STORE );
		$coordinator->resume( self::STORE, RebuildTrigger::Cli, 200, new NullRebuildBatchObserver() );

		$this->assertSame( $pageIds, self::savedPageIdsFrom( $store, $pageIds[0] ) );
	}

	/**
	 * Cancelling does not reach into the queue, because a job for an ended run does nothing — which held
	 * until --resume reopened a run under its old id. A batch still queued from before the cancel then
	 * found the run going again and advanced it alongside the script, each writing over the other's
	 * phase, cursor and counters, so the wiki was walked twice and the totals came out short.
	 */
	public function testABatchQueuedBeforeACancelDoesNotDriveTheRunAShellResumed(): void {
		$pageIds = $this->createSubjectPages( 'One', 'Two', 'Three', 'Four' );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinator( batchSize: 2 );
		$run = $coordinator->startBackground( self::STORE, RebuildTrigger::Api );
		$coordinator->cancel( self::STORE );

		// The job runner pops the batch queued before the cancel while the script is mid-walk, which is
		// the window the run id being reopened creates.
		$stragglerRan = false;
		$store = new SpyGraphDatabasePlugin(
			whileSavingEachPage: function () use ( $run, &$stragglerRan ): void {
				if ( $stragglerRan ) {
					return;
				}

				$stragglerRan = true;
				$this->newCoordinator( batchSize: 2 )->continueInBackground( $run->id );
			}
		);
		$this->registerStore( $store );

		$resumedRun = $this->newCoordinator( batchSize: 2 )
			->resume( self::STORE, RebuildTrigger::Cli, 2, new NullRebuildBatchObserver() );

		$this->assertTrue( $stragglerRan, 'the queued batch has to have been run for this to test anything' );
		$this->assertSame( RebuildStatus::Succeeded, $resumedRun->status );
		$this->assertSame(
			$pageIds,
			self::savedPageIdsFrom( $store, $pageIds[0] ),
			'the queued batch must not have projected pages the script is already walking'
		);
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

		$coordinator->continueInBackground( $run->id );

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

		$this->newCoordinator()->continueInBackground( 123456 );

		$this->assertSame( [], $store->savedPages );
		$this->assertStringContainsString( 'which the records do not have', self::loggedText( $logger ) );
	}

	public function testWhyABackgroundRebuildCouldNotRunABatchIsLogged(): void {
		$logger = new TestLogger( true );
		$this->registerStore( new SpyGraphDatabasePlugin() );
		$coordinator = $this->newCoordinatorWithoutTheStoreItRebuilds( $logger );
		$run = $this->newRunRepository()->startRun( self::STORE, RebuildTrigger::Auto, RebuildStatus::Queued );

		$coordinator->continueInBackground( $run->id );

		$this->assertSame( RebuildStatus::Failed, $this->readRun( $run )?->status );
		$this->assertStringContainsString( 'could not run a batch', self::loggedText( $logger ) );
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
			newPageRebuilder: static fn ( GraphDatabasePlugin $store ) => $extension->newPageRebuilderFor( $store ),
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
			newPageRebuilder: static fn ( GraphDatabasePlugin $store ) => $extension->newPageRebuilderFor( $store ),
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

}
