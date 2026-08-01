<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphRebuild;

use PHPUnit\Framework\TestCase;
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

	public function testReopeningAFailedRunPutsItBackToRunning(): void {
		$run = self::newRun()->failed( 'the store went away' )->reopened();

		$this->assertSame( RebuildStatus::Running, $run->status );
	}

	public function testReopeningAFailedRunClearsTheErrorThatEndedIt(): void {
		$run = self::newRun()->failed( 'the store went away' )->reopened();

		$this->assertNull( $run->error );
	}

	public function testRecordingProgressClearsTheErrorOfTheAttemptBeforeIt(): void {
		$run = self::newRun()->failed( 'the store went away' )->reopened();

		$this->assertNull( $run->withProgress( cursor: 700, processed: 600, failed: 5 )->error );
	}

	public function testASucceededRunCarriesNoError(): void {
		$run = self::newRun()->failed( 'the store went away' )->reopened();

		$this->assertNull( $run->succeeded()->error );
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
		yield 'reopened' => [ self::newRun()->failed( 'the store went away' )->reopened() ];
	}

	private static function newRun(): RebuildRun {
		return new RebuildRun(
			id: 42,
			store: 'EDM',
			status: RebuildStatus::Running,
			cursor: 100,
			processed: 90,
			failed: 2,
			trigger: RebuildTrigger::Cli,
		);
	}

}
