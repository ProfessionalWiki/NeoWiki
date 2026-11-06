<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLockUnavailableException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageProjectionLock;
use RuntimeException;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Advisory locks are re-entrant within a database session, so the second projection is run over a
 * connection of its own, standing for another request.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageProjectionLock
 * @group Database
 */
class DatabasePageProjectionLockTest extends MediaWikiIntegrationTestCase {

	/**
	 * Ids no test page has, so no lock another test left behind is in the way.
	 */
	private const int PAGE = 900001;
	private const int OTHER_PAGE = 900002;

	public function testAnotherProjectionOfThePageIsRefusedWhileOneRuns(): void {
		// SQLite's locks do not hold two connections of one process apart.
		$this->markTestSkippedIfDbType( 'sqlite' );
		$lock = $this->newLock();
		$lockOnAnotherConnection = $this->newLockOnAnotherConnection();

		$this->expectException( PageProjectionLockUnavailableException::class );
		$lock->whileHeld(
			new PageId( self::PAGE ),
			static fn () => $lockOnAnotherConnection->whileHeld( new PageId( self::PAGE ), static fn () => null )
		);
	}

	public function testProjectionsOfDifferentPagesRunSideBySide(): void {
		$projected = $this->newLock()->whileHeld(
			new PageId( self::PAGE ),
			fn () => $this->newLockOnAnotherConnection()->whileHeld( new PageId( self::OTHER_PAGE ), static fn () => true )
		);

		$this->assertTrue( $projected );
	}

	public function testThePageIsFreeAgainAfterAProjectionThrew(): void {
		try {
			$this->newLock()->whileHeld( new PageId( self::PAGE ), static fn () => throw new RuntimeException( 'store down' ) );
		} catch ( RuntimeException ) {
		}

		$projected = $this->newLockOnAnotherConnection()->whileHeld( new PageId( self::PAGE ), static fn () => true );

		$this->assertTrue( $projected );
	}

	/**
	 * A database error leaves the transaction unable to run any query, the lock's release included, until
	 * it is rolled back.
	 */
	public function testThePageIsFreeAgainAfterAProjectionHitADatabaseError(): void {
		$lbFactory = $this->getServiceContainer()->getDBLoadBalancerFactory();
		$lbFactory->beginPrimaryChanges( __METHOD__ );
		$method = __METHOD__;

		try {
			$this->newLock()->whileHeld( new PageId( self::PAGE ), function () use ( $method ): void {
				$this->getDb()->newSelectQueryBuilder()->select( '1' )->from( 'page' )->caller( $method )->fetchField();
				$this->getDb()->query( 'SELEKT 1', $method );
			} );
		} catch ( DBError ) {
		}
		$lbFactory->rollbackPrimaryChanges( __METHOD__ );

		$projected = $this->newLockOnAnotherConnection()->whileHeld( new PageId( self::PAGE ), static fn () => true );

		$this->assertTrue( $projected );
	}

	private function newLock(): DatabasePageProjectionLock {
		return new DatabasePageProjectionLock( $this->getServiceContainer()->getConnectionProvider(), 0 );
	}

	private function newLockOnAnotherConnection(): DatabasePageProjectionLock {
		$connection = $this->getServiceContainer()->getDBLoadBalancerFactory()->newMainLB()->getConnection( DB_PRIMARY );
		$connectionProvider = $this->createStub( IConnectionProvider::class );
		$connectionProvider->method( 'getPrimaryDatabase' )->willReturn( $connection );

		return new DatabasePageProjectionLock( $connectionProvider, 0 );
	}

}
