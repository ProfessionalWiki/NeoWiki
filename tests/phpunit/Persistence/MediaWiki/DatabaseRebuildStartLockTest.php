<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildPhase;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseRebuildStartLock;
use ProfessionalWiki\NeoWiki\Persistence\RebuildStartLock;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseRebuildStartLock
 * @group Database
 */
class DatabaseRebuildStartLockTest extends MediaWikiIntegrationTestCase {

	private const STORE = 'EDM';

	public function testTheRunStartedUnderTheLockIsWhatTheCallerGets(): void {
		$run = self::newRun();

		$this->assertSame( $run, $this->newLock()->whileHeld( self::STORE, static fn (): RebuildRun => $run ) );
	}

	public function testAStoreCanStartAnotherRunOnceItsLastOneIsFiled(): void {
		$lock = $this->newLock();
		$lock->whileHeld( self::STORE, static fn (): RebuildRun => self::newRun() );

		$this->assertStartsARunUnder( $lock );
	}

	/**
	 * The lock has to be let go of even when what it guards throws, or one refused rebuild would leave
	 * that store unable to start another for as long as the connection lived.
	 */
	public function testAStoreCanStartAnotherRunAfterOneFailedToStart(): void {
		$lock = $this->newLock();

		try {
			$lock->whileHeld( self::STORE, static fn (): RebuildRun => throw new RuntimeException( 'no store' ) );
			$this->fail( 'what the lock guards was supposed to throw' );
		} catch ( RuntimeException ) {
		}

		$this->assertStartsARunUnder( $lock );
	}

	/**
	 * That the work runs at all is the assertion: a lock still held, or one the timeout expired on, would
	 * leave it never reached.
	 */
	private function assertStartsARunUnder( RebuildStartLock $lock ): void {
		$started = false;

		$lock->whileHeld( self::STORE, static function () use ( &$started ): RebuildRun {
			$started = true;

			return self::newRun();
		} );

		$this->assertTrue( $started );
	}

	private static function newRun(): RebuildRun {
		return new RebuildRun(
			id: 1,
			store: self::STORE,
			status: RebuildStatus::Queued,
			phase: RebuildPhase::Pages,
			cursor: 0,
			processed: 0,
			failed: 0,
			trigger: RebuildTrigger::Api,
		);
	}

	private function newLock(): RebuildStartLock {
		return new DatabaseRebuildStartLock( $this->getServiceContainer()->getConnectionProvider() );
	}

}
