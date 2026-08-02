<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Where one graph store stands: how far its contents are from the wiki, and what rebuilding it has done
 * about that.
 */
readonly class GraphStoreStatus {

	/**
	 * @param string|null $projection The RDF vocabulary the store holds, or null for a backend that holds
	 *        no RDF at all — Neo4j, and any backend an extension contributed.
	 * @param string|null $projectionChanged When the Mapping defining that projection was last edited.
	 *        Null unless the store is stale, which is the only case it explains.
	 * @param RebuildRun|null $activeRun The rebuild queued or going, if any.
	 * @param RebuildRun|null $lastSuccessfulRun The last rebuild that reconciled the whole wiki, if any.
	 */
	public function __construct(
		public string $name,
		public ?string $projection,
		public StoreSyncState $state,
		public ?string $projectionChanged,
		public ?RebuildRun $activeRun,
		public ?RebuildRun $lastSuccessfulRun,
	) {
	}

}
