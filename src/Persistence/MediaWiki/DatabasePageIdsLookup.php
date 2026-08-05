<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\PageIdsLookup;
use Wikimedia\Rdbms\IReadableDatabase;

class DatabasePageIdsLookup implements PageIdsLookup {

	public const int BATCH_SIZE = 500;

	public function __construct(
		private readonly IReadableDatabase $db,
	) {
	}

	/**
	 * Keyset pagination over page_id, the page table's primary key: each batch seeks past the last id
	 * seen, so walking the wiki costs one indexed query per batch and holds one batch of ids in memory
	 * rather than the whole page table.
	 *
	 * @return iterable<int>
	 */
	public function getPageIds( int $afterPageId = 0 ): iterable {
		$lastPageId = $afterPageId;

		do {
			$pageIds = $this->getPageIdsAfter( $lastPageId, self::BATCH_SIZE );

			foreach ( $pageIds as $pageId ) {
				$lastPageId = $pageId;
				yield $pageId;
			}
		} while ( count( $pageIds ) === self::BATCH_SIZE );
	}

	/**
	 * @return int[]
	 */
	public function getPageIdsAfter( int $afterPageId, int $limit ): array {
		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->where( $this->db->buildComparison( '>', [ 'page_id' => $afterPageId ] ) )
			->orderBy( 'page_id' )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	public function countPages(): int {
		$count = $this->db->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'page' )
			->caller( __METHOD__ )
			->fetchField();

		return is_numeric( $count ) ? (int)$count : 0;
	}

}
