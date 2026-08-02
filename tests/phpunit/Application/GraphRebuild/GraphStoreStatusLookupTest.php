<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\GraphRebuild;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphStoreStatus;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphStoreStatusLookup;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\StoreSyncState;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseRebuildRunRepository;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryProjectionChangeTimeLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphStoreStatusLookup
 * @group Database
 */
class GraphStoreStatusLookupTest extends MediaWikiIntegrationTestCase {

	private const SPARQL_STORE = 'EDM';
	private const PROJECTION = 'EDM';
	private const NEO4J_STORE = 'neo4j';
	private const NATIVE_STORE = 'native-store';

	public function testAStoreNoRebuildEverFinishedWasNeverBuilt(): void {
		$this->assertSame( StoreSyncState::NeverBuilt, $this->statusOf( self::SPARQL_STORE )->state );
	}

	public function testAStoreWhoseOnlyRebuildFailedWasNeverBuilt(): void {
		$repository = $this->newRunRepository();
		$repository->updateRun( $this->startRun( $repository, self::SPARQL_STORE )->failed( 'the store went away' ) );

		$this->assertSame( StoreSyncState::NeverBuilt, $this->statusOf( self::SPARQL_STORE )->state );
	}

	public function testARebuiltStoreWhoseMappingHasNotChangedSinceIsInSync(): void {
		$rebuild = $this->recordSucceededRun( self::SPARQL_STORE );

		$status = $this->statusOf( self::SPARQL_STORE, projectionChanged: self::justBefore( $rebuild ) );

		$this->assertSame( StoreSyncState::InSync, $status->state );
		$this->assertNull( $status->projectionChanged );
	}

	/**
	 * The edit is measured against when the rebuild *started*, not when it finished: a Mapping edited
	 * while the rebuild was walking the wiki leaves the pages it had already passed projected under the
	 * old rules.
	 */
	public function testARebuiltStoreWhoseMappingChangedAfterTheRebuildBeganIsStale(): void {
		$rebuild = $this->recordSucceededRun( self::SPARQL_STORE );
		$mappingEdited = self::justAfter( $rebuild );

		$status = $this->statusOf( self::SPARQL_STORE, projectionChanged: $mappingEdited );

		$this->assertSame( StoreSyncState::Stale, $status->state );
		$this->assertSame( $mappingEdited, $status->projectionChanged );
	}

	/**
	 * Neo4j holds no RDF, so it has no Mapping page that could move under it: once rebuilt it stays as
	 * current as the per-edit projection keeps it.
	 */
	public function testARebuiltGraphBackendHoldingNoRdfIsInSync(): void {
		$rebuild = $this->recordSucceededRun( self::NEO4J_STORE );

		$status = $this->statusOf( self::NEO4J_STORE, projectionChanged: self::justAfter( $rebuild ) );

		$this->assertSame( StoreSyncState::InSync, $status->state );
		$this->assertNull( $status->projection );
	}

	/**
	 * The native projection is defined by NeoWiki's own code, not by a page anyone can edit.
	 */
	public function testARebuiltNativeProjectionStoreIsInSync(): void {
		$rebuild = $this->recordSucceededRun( self::NATIVE_STORE );

		$status = $this->statusOf( self::NATIVE_STORE, projectionChanged: self::justAfter( $rebuild ) );

		$this->assertSame( StoreSyncState::InSync, $status->state );
		$this->assertSame( RdfPageProjector::PROJECTION, $status->projection );
	}

	public function testAProjectionNoMappingDefinesLeavesTheStoreInSync(): void {
		$this->recordSucceededRun( self::SPARQL_STORE );

		$status = $this->statusOf( self::SPARQL_STORE, projectionChanged: null );

		$this->assertSame( StoreSyncState::InSync, $status->state );
	}

	public function testTheRebuildAStoreHasGoingIsReported(): void {
		$queuedRun = $this->startRun( $this->newRunRepository(), self::SPARQL_STORE, RebuildStatus::Queued );

		$status = $this->statusOf( self::SPARQL_STORE );

		$this->assertSame( $queuedRun->id, $status->activeRun?->id );
		$this->assertSame( RebuildStatus::Queued, $status->activeRun->status );
	}

	public function testAStoreWithNoRebuildGoingReportsNone(): void {
		$this->recordSucceededRun( self::SPARQL_STORE );

		$this->assertNull( $this->statusOf( self::SPARQL_STORE )->activeRun );
	}

	public function testWhatTheLastFinishedRebuildGotThroughIsReported(): void {
		$repository = $this->newRunRepository();
		$run = $this->startRun( $repository, self::SPARQL_STORE );
		$repository->updateRun( $run->withProgress( cursor: 90, processed: 88, failed: 2 )->succeeded() );

		$lastSuccessfulRun = $this->statusOf( self::SPARQL_STORE )->lastSuccessfulRun;

		$this->assertSame( 88, $lastSuccessfulRun?->processed );
		$this->assertSame( 2, $lastSuccessfulRun->failed );
		$this->assertNotNull( $lastSuccessfulRun->finished );
	}

	public function testEveryConfiguredStoreIsReportedInConfigurationOrder(): void {
		$this->assertSame(
			[ self::NEO4J_STORE, self::SPARQL_STORE, self::NATIVE_STORE ],
			array_map(
				static fn ( GraphStoreStatus $status ): string => $status->name,
				$this->newLookup()->getStatuses()
			)
		);
	}

	public function testAStoreThisWikiHasNotConfiguredHasNoStatus(): void {
		$this->assertNull( $this->newLookup()->getStatus( 'no-such-store' ) );
	}

	private function statusOf( string $storeName, ?string $projectionChanged = null ): GraphStoreStatus {
		$status = $this->newLookup( $projectionChanged )->getStatus( $storeName );
		$this->assertNotNull( $status );

		return $status;
	}

	private function newLookup( ?string $projectionChanged = null ): GraphStoreStatusLookup {
		return new GraphStoreStatusLookup(
			projectionsByStore: [
				self::NEO4J_STORE => null,
				self::SPARQL_STORE => self::PROJECTION,
				self::NATIVE_STORE => RdfPageProjector::PROJECTION,
			],
			runs: $this->newRunRepository(),
			// The native projection is given a change time too, so a store holding it is reported in sync
			// because nothing defines it — not because the fake happened to know nothing about it.
			projectionChanges: new InMemoryProjectionChangeTimeLookup( [
				self::PROJECTION => $projectionChanged,
				RdfPageProjector::PROJECTION => $projectionChanged,
			] ),
		);
	}

	private function recordSucceededRun( string $storeName ): RebuildRun {
		$repository = $this->newRunRepository();
		$repository->updateRun( $this->startRun( $repository, $storeName )->succeeded() );

		$storedRun = $repository->getLastSuccessfulRun( $storeName );
		$this->assertNotNull( $storedRun?->started );

		return $storedRun;
	}

	private function startRun(
		RebuildRunRepository $repository,
		string $storeName,
		RebuildStatus $status = RebuildStatus::Running
	): RebuildRun {
		return $repository->startRun( $storeName, RebuildTrigger::Cli, $status );
	}

	private static function justBefore( RebuildRun $run ): string {
		return (string)( (int)$run->started - 1 );
	}

	private static function justAfter( RebuildRun $run ): string {
		return (string)( (int)$run->started + 1 );
	}

	private function newRunRepository(): RebuildRunRepository {
		return new DatabaseRebuildRunRepository( $this->getServiceContainer()->getConnectionProvider() );
	}

}
