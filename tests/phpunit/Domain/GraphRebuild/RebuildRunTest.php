<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphRebuild;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildPhase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun
 */
class RebuildRunTest extends TestCase {

	public function testProgressIsWhatWasRecorded(): void {
		$run = self::newRun()->withProgress( cursor: 700, processed: 600, failed: 5 );

		$this->assertSame( 700, $run->cursor );
		$this->assertSame( 600, $run->processed );
		$this->assertSame( 5, $run->failed );
	}

	public function testRecordingProgressLeavesTheRunGoing(): void {
		$run = self::newRun()->withProgress( cursor: 700, processed: 600, failed: 5 );

		$this->assertSame( RebuildStatus::Running, $run->status );
	}

	public function testAFailedRunCarriesTheErrorThatEndedIt(): void {
		$this->assertSame( 'the store went away', self::newRun()->failed( 'the store went away' )->error );
	}

	/**
	 * A run that stopped partway is what --resume picks up, so what it got through has to survive the
	 * transition that ends it.
	 */
	public function testAFailedRunKeepsWhatItGotThroughBeforeItStopped(): void {
		$run = self::newRun()->failed( 'the store went away' );

		$this->assertSame( 100, $run->cursor );
		$this->assertSame( 90, $run->processed );
		$this->assertSame( 2, $run->failed );
	}

	public function testStartingAFailedRunAgainPutsItBackToRunning(): void {
		$run = self::newRun()->failed( 'the store went away' )->started();

		$this->assertSame( RebuildStatus::Running, $run->status );
	}

	public function testStartingAFailedRunAgainClearsTheErrorThatEndedIt(): void {
		$run = self::newRun()->failed( 'the store went away' )->started();

		$this->assertNull( $run->error );
	}

	public function testRecordingProgressClearsTheErrorOfTheAttemptBeforeIt(): void {
		$run = self::newRun()->failed( 'the store went away' )->started();

		$this->assertNull( $run->withProgress( cursor: 700, processed: 600, failed: 5 )->error );
	}

	public function testASucceededRunCarriesNoError(): void {
		$run = self::newRun()->failed( 'the store went away' )->started();

		$this->assertNull( $run->succeeded()->error );
	}

	/**
	 * The two phases walk different sets of page ids, so carrying the page cursor into the second would
	 * have it start past pages it has never looked at.
	 */
	public function testEnteringTheDeletionPhaseRestartsTheCursor(): void {
		$run = self::newRun()->enteredDeletionPhase();

		$this->assertSame( RebuildPhase::Deletions, $run->phase );
		$this->assertSame( 0, $run->cursor );
	}

	public function testEnteringTheDeletionPhaseKeepsWhatThePageWalkGotThrough(): void {
		$run = self::newRun()->enteredDeletionPhase();

		$this->assertSame( 90, $run->processed );
		$this->assertSame( 2, $run->failed );
	}

	/**
	 * A rebuild interrupted during its removals must not restart at the wiki's pages, or every page would
	 * be projected a second time before it got back to where it was.
	 */
	public function testStartingARunAgainKeepsThePhaseItStoppedIn(): void {
		$run = self::newRun()->enteredDeletionPhase()->withProgress( cursor: 12, processed: 90, failed: 2 )
			->failed( 'the store went away' )->started();

		$this->assertSame( RebuildPhase::Deletions, $run->phase );
		$this->assertSame( 12, $run->cursor );
	}

	public function testAQueuedRunThatStartsIsRunning(): void {
		$run = self::newQueuedRun()->started();

		$this->assertSame( RebuildStatus::Running, $run->status );
	}

	public function testACancelledRunKeepsWhatItGotThroughBeforeItWasStopped(): void {
		$run = self::newRun()->cancelled();

		$this->assertSame( RebuildStatus::Cancelled, $run->status );
		$this->assertSame( 100, $run->cursor );
		$this->assertSame( 90, $run->processed );
	}

	/**
	 * @dataProvider transitionProvider
	 */
	public function testWhichRunItIsSurvivesEveryTransition( RebuildRun $run ): void {
		$this->assertSame( 42, $run->id );
		$this->assertSame( 'EDM', $run->store );
		$this->assertSame( RebuildTrigger::Cli, $run->trigger );
	}

	public static function transitionProvider(): iterable {
		yield 'progress recorded' => [ self::newRun()->withProgress( cursor: 700, processed: 600, failed: 5 ) ];
		yield 'succeeded' => [ self::newRun()->succeeded() ];
		yield 'failed' => [ self::newRun()->failed( 'the store went away' ) ];
		yield 'started again' => [ self::newRun()->failed( 'the store went away' )->started() ];
		yield 'cancelled' => [ self::newRun()->cancelled() ];
		yield 'entered the deletion phase' => [ self::newRun()->enteredDeletionPhase() ];
	}

	private static function newRun( RebuildStatus $status = RebuildStatus::Running ): RebuildRun {
		return new RebuildRun(
			id: 42,
			store: 'EDM',
			status: $status,
			phase: RebuildPhase::Pages,
			cursor: 100,
			processed: 90,
			failed: 2,
			trigger: RebuildTrigger::Cli,
		);
	}

	private static function newQueuedRun(): RebuildRun {
		return self::newRun( RebuildStatus::Queued );
	}

}
