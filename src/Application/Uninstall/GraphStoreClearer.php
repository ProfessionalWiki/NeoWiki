<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Uninstall;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\DeletedPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\PageIdsLookup;

/**
 * Takes this wiki's pages out of a graph store, one page at a time, because a store can be shared with
 * other wikis and with data that is not NeoWiki's. Deleted pages are included, since a store that missed
 * a deletion still holds them.
 */
class GraphStoreClearer {

	public function __construct(
		private readonly PageIdsLookup $pageIdsLookup,
		private readonly DeletedPageIdsLookup $deletedPageIdsLookup,
		private readonly int $batchSize,
	) {
	}

	/**
	 * Throws what the store throws when it cannot be reached, leaving the pages after it in place.
	 *
	 * @return iterable<int> How many pages have been removed so far, once per batch.
	 */
	public function clear( GraphDatabasePlugin $store ): iterable {
		$removed = 0;

		foreach ( $this->pageIdBatches() as $pageIds ) {
			foreach ( $pageIds as $pageId ) {
				$store->deletePage( new PageId( $pageId ) );
			}

			$removed += count( $pageIds );
			yield $removed;
		}
	}

	/**
	 * @return iterable<int[]>
	 */
	private function pageIdBatches(): iterable {
		yield from $this->batchesOf(
			fn ( int $after ): array => $this->pageIdsLookup->getPageIdsAfter( $after, $this->batchSize )
		);
		yield from $this->batchesOf(
			fn ( int $after ): array => $this->deletedPageIdsLookup->getDeletedPageIdsAfter( $after, $this->batchSize )
		);
	}

	/**
	 * @param callable(int): int[] $pageIdsAfter
	 * @return iterable<int[]>
	 */
	private function batchesOf( callable $pageIdsAfter ): iterable {
		for ( $pageIds = $pageIdsAfter( 0 ); $pageIds !== []; $pageIds = $pageIdsAfter( max( $pageIds ) ) ) {
			yield $pageIds;
		}
	}

}
