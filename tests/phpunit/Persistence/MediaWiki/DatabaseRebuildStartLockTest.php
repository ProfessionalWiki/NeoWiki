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
	private const LOCK_NAME = 'NeoWiki-graph-rebuild-start:' . self::STORE;

	public function testTheRunStartedUnderTheLockIsWhatTheCallerGets(): void {
		$run = self::newRun();

		$this->assertSame( $run, $this->newLock()->whileHeld( self::STORE, static fn (): RebuildRun => $run ) );
	}

	public function testAStoresLockIsReleasedOnceItsRunIsStarted(): void {
		$this->newLock()->whileHeld( self::STORE, static fn (): RebuildRun => self::newRun() );

		$this->assertLockIsFree();
	}

	/**
	 * The lock has to be released even when what it guards throws, or one refused rebuild would leave
	 * that store unable to start another for as long as the connection lived.
	 */
	public function testTheLockIsReleasedWhenStartingTheRunThrows(): void {
		try {
			$this->newLock()->whileHeld(
				self::STORE,
				static fn (): RebuildRun => throw new RuntimeException( 'no store' )
			);
			$this->fail( 'what the lock guards was supposed to throw' );
		} catch ( RuntimeException ) {
		}

		$this->assertLockIsFree();
	}

	public function testTheLockIsHeldWhileTheRunIsBeingStarted(): void {
		$caller = __METHOD__;

		$this->newLock()->whileHeld( self::STORE, function () use ( $caller ): RebuildRun {
			$this->assertFalse(
				$this->getDb()->lockIsFree( self::LOCK_NAME, $caller ),
				'the store must be locked while its run is filed'
			);

			return self::newRun();
		} );
	}

	private function assertLockIsFree(): void {
		$this->assertTrue( $this->getDb()->lockIsFree( self::LOCK_NAME, __METHOD__ ) );
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
