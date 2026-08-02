<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;

/**
 * Works out where each configured graph store stands, from the run records and the wiki.
 *
 * A store that has never been rebuilt through to the end holds only what the per-edit projection has put
 * there, which for a store added to a wiki that already had pages is nothing: that is reported ahead of
 * anything else, because rebuilding is the answer either way.
 *
 * Beyond that, only a store holding an ontology projection can fall out of date without the wiki
 * changing, because only that projection is defined by something editable: the Mapping page. Editing it
 * changes what every mapped page's graph should contain, and nothing reprojects those pages. The native
 * projection and a backend holding no RDF at all have no such definition, so once rebuilt they stay as
 * current as the per-edit projection keeps them.
 */
class GraphStoreStatusLookup {

	/**
	 * @param array<string, ?string> $projectionsByStore Keys are store names; the value is the RDF
	 *        vocabulary that store holds, or null for a backend holding no RDF
	 */
	public function __construct(
		private readonly array $projectionsByStore,
		private readonly RebuildRunRepository $runs,
		private readonly ProjectionChangeTimeLookup $projectionChanges,
	) {
	}

	/**
	 * @return GraphStoreStatus[] One per configured store, in configuration order
	 */
	public function getStatuses(): array {
		return array_map(
			fn ( string $storeName ): GraphStoreStatus => $this->newStatus( $storeName ),
			array_keys( $this->projectionsByStore )
		);
	}

	public function getStatus( string $storeName ): ?GraphStoreStatus {
		return array_key_exists( $storeName, $this->projectionsByStore )
			? $this->newStatus( $storeName )
			: null;
	}

	private function newStatus( string $storeName ): GraphStoreStatus {
		$projection = $this->projectionsByStore[$storeName];
		$lastSuccessfulRun = $this->runs->getLastSuccessfulRun( $storeName );
		$projectionChanged = $this->projectionChangedSince( $projection, $lastSuccessfulRun );

		return new GraphStoreStatus(
			name: $storeName,
			projection: $projection,
			state: self::stateOf( $lastSuccessfulRun, $projectionChanged ),
			projectionChanged: $projectionChanged,
			activeRun: $this->runs->getActiveRun( $storeName ),
			lastSuccessfulRun: $lastSuccessfulRun,
		);
	}

	private static function stateOf( ?RebuildRun $lastSuccessfulRun, ?string $projectionChanged ): StoreSyncState {
		if ( $lastSuccessfulRun === null ) {
			return StoreSyncState::NeverBuilt;
		}

		return $projectionChanged === null ? StoreSyncState::InSync : StoreSyncState::Stale;
	}

	/**
	 * When the store's projection was redefined after the last rebuild of it began, or null when it was
	 * not. Measured against when that rebuild *started* rather than when it finished, because a Mapping
	 * edited while a rebuild was walking the wiki leaves the pages it had already passed projected under
	 * the old rules.
	 */
	private function projectionChangedSince( ?string $projection, ?RebuildRun $lastSuccessfulRun ): ?string {
		if ( $projection === null || $projection === RdfPageProjector::PROJECTION || $lastSuccessfulRun === null ) {
			return null;
		}

		$changed = $this->projectionChanges->getLastChangeTime( $projection );

		if ( $changed === null || $lastSuccessfulRun->started === null || $changed <= $lastSuccessfulRun->started ) {
			return null;
		}

		return $changed;
	}

}
