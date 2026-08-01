<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWikiIntegrationTestCase;
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
		$run = $this->newRepository()->startRun( self::STORE, RebuildTrigger::Cli );

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

		$first = $repository->startRun( self::STORE, RebuildTrigger::Cli );
		$second = $repository->startRun( self::OTHER_STORE, RebuildTrigger::Cli );

		$this->assertNotSame( $first->id, $second->id );
	}

	public function testProgressIsReadBackAsItWasWritten(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );

		$repository->updateRun( $run->withProgress( cursor: 4200, processed: 37, failed: 5 ) );

		$storedRun = $repository->getActiveRun( self::STORE );
		$this->assertNotNull( $storedRun );
		$this->assertSame( 4200, $storedRun->cursor );
		$this->assertSame( 37, $storedRun->processed );
		$this->assertSame( 5, $storedRun->failed );
	}

	public function testTheErrorThatFailedARunIsReadBack(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );

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
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );

		$repository->updateRun( $run->failed( str_repeat( '★', 30000 ) ) );

		$this->assertSame( str_repeat( '★', 21843 ), $repository->getLatestRun( self::STORE )?->error );
	}

	public function testAStoreWithNoRunsHasNoActiveRun(): void {
		$this->assertNull( $this->newRepository()->getActiveRun( self::STORE ) );
	}

	public function testAStoreWithNoRunsHasNoLatestRun(): void {
		$this->assertNull( $this->newRepository()->getLatestRun( self::STORE ) );
	}

	public function testAFinishedRunIsNoLongerActive(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );

		$repository->updateRun( $run->succeeded() );

		$this->assertNull( $repository->getActiveRun( self::STORE ) );
	}

	public function testAFinishedRunIsStillTheLatestOne(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );
		$repository->updateRun( $run->succeeded() );

		$latestRun = $repository->getLatestRun( self::STORE );

		$this->assertNotNull( $latestRun );
		$this->assertSame( $run->id, $latestRun->id );
		$this->assertSame( RebuildStatus::Succeeded, $latestRun->status );
	}

	public function testTheLatestRunIsTheOneStartedLast(): void {
		$repository = $this->newRepository();
		$repository->updateRun( $repository->startRun( self::STORE, RebuildTrigger::Cli )->succeeded() );
		$repository->updateRun( $repository->startRun( self::STORE, RebuildTrigger::Cli )->failed( 'boom' ) );
		$lastStartedRun = $repository->startRun( self::STORE, RebuildTrigger::Cli );
		$repository->updateRun( $lastStartedRun->succeeded() );

		$latestRun = $repository->getLatestRun( self::STORE );

		$this->assertNotNull( $latestRun );
		$this->assertSame( $lastStartedRun->id, $latestRun->id );
	}

	public function testAnotherStoresRunIsNotThisStoresActiveRun(): void {
		$repository = $this->newRepository();
		$repository->startRun( self::OTHER_STORE, RebuildTrigger::Cli );

		$this->assertNull( $repository->getActiveRun( self::STORE ) );
	}

	public function testAnotherStoresRunIsNotThisStoresLatestRun(): void {
		$repository = $this->newRepository();
		$repository->startRun( self::OTHER_STORE, RebuildTrigger::Cli );

		$this->assertNull( $repository->getLatestRun( self::STORE ) );
	}

	public function testARunningRunHasNotEndedYet(): void {
		$run = $this->newRepository()->startRun( self::STORE, RebuildTrigger::Cli );

		$this->assertNull( $this->readFinishedTime( $run ) );
	}

	public function testAFinishedRunRecordsWhenItEnded(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );

		$repository->updateRun( $run->succeeded() );

		$this->assertNotNull( $this->readFinishedTime( $run ) );
	}

	public function testReopeningARunClearsWhenItEnded(): void {
		$repository = $this->newRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli );
		$repository->updateRun( $run->failed( 'boom' ) );

		$repository->updateRun( $run->reopened() );

		$this->assertNull( $this->readFinishedTime( $run ) );
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
