<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildPhase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseRebuildRunRepository;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseRebuildRunRepository
 * @group Database
 */
class DatabaseRebuildRunRepositoryTest extends MediaWikiIntegrationTestCase {

	private const STORE = 'neo4j';
	private const OTHER_STORE = 'EDM';

	public function testAStartedRunIsRunningWithNothingDoneYet(): void {
		$run = $this->newRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertSame( self::STORE, $run->store );
		$this->assertSame( RebuildStatus::Running, $run->status );
		$this->assertSame( RebuildTrigger::Cli, $run->trigger );
		$this->assertSame( 0, $run->cursor );
		$this->assertSame( 0, $run->processed );
		$this->assertSame( 0, $run->failed );
		$this->assertNull( $run->error );
	}

	public function testEachStartedRunGetsItsOwnId(): void {
		$repository = $this->newRepository();

		$first = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$second = $repository->startRun( self::OTHER_STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertNotSame( $first->id, $second->id );
	}

	public function testProgressIsReadBackAsItWasWritten(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$repository->updateRun( $run->withProgress( cursor: 4200, processed: 37, failed: 5 ) );

		$storedRun = $repository->getActiveRun( self::STORE );
		$this->assertNotNull( $storedRun );
		$this->assertSame( 4200, $storedRun->cursor );
		$this->assertSame( 37, $storedRun->processed );
		$this->assertSame( 5, $storedRun->failed );
	}

	public function testTheErrorThatFailedARunIsReadBack(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$repository->updateRun( $run->failed( 'the store went away' ) );

		$storedRun = $repository->getLatestRun( self::STORE );
		$this->assertNotNull( $storedRun );
		$this->assertSame( RebuildStatus::Failed, $storedRun->status );
		$this->assertSame( 'the store went away', $storedRun->error );
	}

	/**
	 * A driver quoting the query it choked on runs far longer than the error column holds, and losing
	 * the tail of a message beats losing the record of the failure. Cutting bytes rather than characters
	 * would leave a broken one at the end of what is kept.
	 */
	public function testAnErrorTooLongForTheColumnIsCutAtACharacterBoundary(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$repository->updateRun( $run->failed( str_repeat( '★', 30000 ) ) );

		$this->assertSame( str_repeat( '★', 21843 ), $repository->getLatestRun( self::STORE )?->error );
	}

	public function testAStartedRunBeginsAtTheWikisPages(): void {
		$run = $this->newRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertSame( RebuildPhase::Pages, $run->phase );
	}

	public function testARunRecordsWhenItStarted(): void {
		$run = $this->newRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertNotNull( $run->started );
	}

	public function testTheStoredRunSaysWhenItStartedAndFinished(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->succeeded() );

		$storedRun = $repository->getLatestRun( self::STORE );

		$this->assertSame( $run->started, $storedRun?->started, 'the start time survives the round trip' );
		$this->assertNotNull( $storedRun->finished );
	}

	/**
	 * Every comparison and every display of these is written against MediaWiki timestamps, but a database
	 * hands its timestamp columns back in whatever format it stores them in.
	 */
	public function testTimesAreReadBackAsMediaWikiTimestamps(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->succeeded() );

		$storedRun = $repository->getLatestRun( self::STORE );

		$this->assertMatchesRegularExpression( '/^\d{14}$/', (string)$storedRun?->started );
		$this->assertMatchesRegularExpression( '/^\d{14}$/', (string)$storedRun->finished );
	}

	public function testThePhaseARunReachedIsReadBack(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$repository->updateRun( $run->enteredDeletionPhase() );

		$this->assertSame( RebuildPhase::Deletions, $repository->getActiveRun( self::STORE )?->phase );
	}

	/**
	 * How a process that did not start a run — a background batch — finds out what has become of it.
	 */
	public function testARunIsReadBackById(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->cancelled() );

		$storedRun = $repository->getRun( $run->id );

		$this->assertSame( $run->id, $storedRun?->id );
		$this->assertSame( RebuildStatus::Cancelled, $storedRun->status );
	}

	public function testThereIsNoRunUnderAnIdNothingWasFiledUnder(): void {
		$this->assertNull( $this->newRepository()->getRun( 123456 ) );
	}

	/**
	 * A store has one rebuild ahead of it whether or not anything has begun working on it, so a queued
	 * run has to block a second one just as a running one does.
	 */
	public function testAQueuedRunIsTheStoresActiveRun(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Api, RebuildStatus::Queued );

		$activeRun = $repository->getActiveRun( self::STORE );

		$this->assertSame( $run->id, $activeRun?->id );
		$this->assertSame( RebuildStatus::Queued, $activeRun->status );
	}

	public function testAStoreThatWasNeverRebuiltToTheEndHasNoSuccessfulRun(): void {
		$repository = $this->newRepository();
		$repository->updateRun(
			$repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )->failed( 'boom' )
		);

		$this->assertNull( $repository->getLastSuccessfulRun( self::STORE ) );
	}

	public function testTheLastSuccessfulRunIsTheLastOneThatSucceeded(): void {
		$repository = $this->newRepository();
		$repository->updateRun(
			$repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )->succeeded()
		);
		$lastSucceeded = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $lastSucceeded->succeeded() );
		$repository->updateRun(
			$repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )->failed( 'boom' )
		);

		$this->assertSame( $lastSucceeded->id, $repository->getLastSuccessfulRun( self::STORE )?->id );
	}

	public function testAStoreWithNoRunsHasNoActiveRun(): void {
		$this->assertNull( $this->newRepository()->getActiveRun( self::STORE ) );
	}

	public function testAStoreWithNoRunsHasNoLatestRun(): void {
		$this->assertNull( $this->newRepository()->getLatestRun( self::STORE ) );
	}

	public function testAFinishedRunIsNoLongerActive(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$repository->updateRun( $run->succeeded() );

		$this->assertNull( $repository->getActiveRun( self::STORE ) );
	}

	public function testAFinishedRunIsStillTheLatestOne(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->succeeded() );

		$latestRun = $repository->getLatestRun( self::STORE );

		$this->assertNotNull( $latestRun );
		$this->assertSame( $run->id, $latestRun->id );
		$this->assertSame( RebuildStatus::Succeeded, $latestRun->status );
	}

	public function testTheLatestRunIsTheOneStartedLast(): void {
		$repository = $this->newRepository();
		$repository->updateRun( $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )->succeeded() );
		$repository->updateRun( $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running )->failed( 'boom' ) );
		$lastStartedRun = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $lastStartedRun->succeeded() );

		$latestRun = $repository->getLatestRun( self::STORE );

		$this->assertNotNull( $latestRun );
		$this->assertSame( $lastStartedRun->id, $latestRun->id );
	}

	public function testAnotherStoresRunIsNotThisStoresActiveRun(): void {
		$repository = $this->newRepository();
		$repository->startRun( self::OTHER_STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertNull( $repository->getActiveRun( self::STORE ) );
	}

	public function testAnotherStoresRunIsNotThisStoresLatestRun(): void {
		$repository = $this->newRepository();
		$repository->startRun( self::OTHER_STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertNull( $repository->getLatestRun( self::STORE ) );
	}

	public function testARunningRunHasNotEndedYet(): void {
		$run = $this->newRepository()->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->assertNull( $this->readFinishedTime( $run ) );
	}

	public function testAFinishedRunRecordsWhenItEnded(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$repository->updateRun( $run->succeeded() );

		$this->assertNotNull( $this->readFinishedTime( $run ) );
	}

	public function testPickingAnEndedRunBackUpClearsWhenItEnded(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->failed( 'boom' ) );

		$repository->updateRun( $run->started() );

		$this->assertNull( $this->readFinishedTime( $run ) );
	}

	/**
	 * A batch reads the run, spends minutes projecting, and writes back what it got through. Whether that
	 * write landed is what tells it the run is still its own to advance.
	 */
	public function testWritingBackARunThatIsStillGoingReportsTheRunAsWritten(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$writtenRun = $repository->updateRunWhileActive( $run->withProgress( cursor: 7, processed: 7, failed: 0 ) );

		$this->assertSame( 7, $writtenRun?->processed );
		$this->assertSame( RebuildStatus::Running, $writtenRun->status );
	}

	/**
	 * What the caller does next depends on which status ended the run, so it is reported rather than left
	 * to a second read — which, inside a transaction opened before the cancellation, would still see the
	 * run as going.
	 *
	 * The cancellation is made on this connection, because the harness wraps each test in one transaction
	 * on one connection: a genuinely concurrent one, from a connection whose commit this one's snapshot
	 * predates, cannot be produced from inside a test. What is asserted here is that the write is refused
	 * and what refused it is reported; that the refusal is seen at all across connections is what the
	 * affected-row count and the locking read are for.
	 */
	public function testWritingBackARunSomethingElseEndedReportsWhatEndedIt(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->cancelled() );

		$storedRun = $repository->updateRunWhileActive( $run->withProgress( cursor: 7, processed: 7, failed: 0 ) );

		$this->assertSame( RebuildStatus::Cancelled, $storedRun?->status );
		$this->assertSame( 0, $storedRun->processed, 'what the refused batch got through is not written' );
	}

	public function testCancellingAStoresRebuildEndsTheRunItHadGoing(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Api, RebuildStatus::Queued );

		$cancelledRun = $repository->cancelActiveRun( self::STORE );

		$this->assertSame( $run->id, $cancelledRun?->id );
		$this->assertSame( RebuildStatus::Cancelled, $cancelledRun->status );
		$this->assertNull( $repository->getActiveRun( self::STORE ) );
	}

	/**
	 * A run reaching the end of the wiki while it is being cancelled must keep having done so. Read first
	 * and written back after, the cancellation would land on top of it, and the store would then be
	 * reported as never rebuilt.
	 */
	public function testCancellingAStoreWhoseRunHasSinceSucceededLeavesItSucceeded(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->succeeded() );

		$this->assertNull( $repository->cancelActiveRun( self::STORE ) );
		$this->assertSame( RebuildStatus::Succeeded, $repository->getLatestRun( self::STORE )?->status );
	}

	public function testCancellingAStoreWithNoRebuildEndsNothing(): void {
		$this->assertNull( $this->newRepository()->cancelActiveRun( self::STORE ) );
	}

	public function testCancellingAStoreLeavesAnotherStoresRebuildGoing(): void {
		$repository = $this->newRepository();
		$repository->startRun( self::OTHER_STORE, RebuildTrigger::Api, RebuildStatus::Queued );

		$repository->cancelActiveRun( self::STORE );

		$this->assertNotNull( $repository->getActiveRun( self::OTHER_STORE ) );
	}

	/**
	 * A batch may have advanced the run since whoever pressed cancel last read it, so what the cancelled
	 * run says it got through is the record's, not theirs.
	 */
	public function testCancellingKeepsHowFarTheRunHadGot(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->withProgress( cursor: 40, processed: 37, failed: 2 ) );

		$cancelledRun = $repository->cancelActiveRun( self::STORE );

		$this->assertSame( 40, $cancelledRun?->cursor );
		$this->assertSame( 37, $cancelledRun->processed );
		$this->assertSame( 2, $cancelledRun->failed );
	}

	public function testWritingBackARunNothingWasFiledUnderReportsNoRun(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$this->deleteRunRow( $run );

		$this->assertNull( $repository->updateRunWhileActive( $run->withProgress( cursor: 7, processed: 7, failed: 0 ) ) );
	}

	private function deleteRunRow( RebuildRun $run ): void {
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'neowiki_rebuild_runs' )
			->where( [ 'nwrr_id' => $run->id ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function readFinishedTime( RebuildRun $run ): ?string {
		$finished = $this->getDb()->newSelectQueryBuilder()
			->select( 'nwrr_finished' )
			->from( 'neowiki_rebuild_runs' )
			->where( [ 'nwrr_id' => $run->id ] )
			->caller( __METHOD__ )
			->fetchField();

		return $finished === false || $finished === null ? null : (string)$finished;
	}

	private function newRepository(): RebuildRunRepository {
		return new DatabaseRebuildRunRepository( $this->getServiceContainer()->getConnectionProvider() );
	}

}
