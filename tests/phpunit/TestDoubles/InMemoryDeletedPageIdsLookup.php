<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Persistence\DeletedPageIdsLookup;

class InMemoryDeletedPageIdsLookup implements DeletedPageIdsLookup {

	/**
	 * @var int[]
	 */
	private array $pageIds;

	public function __construct( int ...$pageIds ) {
		$this->pageIds = $pageIds;
	}

	/**
	 * @return iterable<int>
	 */
	public function getDeletedPageIds(): iterable {
		yield from $this->pageIds;
	}

	/**
	 * @return int[]
	 */
	public function getDeletedPageIdsAfter( int $afterPageId, int $limit ): array {
		$remaining = array_filter( $this->pageIds, static fn ( int $pageId ): bool => $pageId > $afterPageId );

		return array_slice( array_values( $remaining ), 0, $limit );
	}

	public function countDeletedPages(): int {
		return count( $this->pageIds );
	}

}
