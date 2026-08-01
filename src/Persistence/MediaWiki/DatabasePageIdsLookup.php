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
	public function getPageIds(): iterable {
		$lastPageId = 0;

		do {
			$pageIds = $this->db->newSelectQueryBuilder()
				->select( 'page_id' )
				->from( 'page' )
				->where( $this->db->expr( 'page_id', '>', $lastPageId ) )
				->orderBy( 'page_id' )
				->limit( self::BATCH_SIZE )
				->caller( __METHOD__ )
				->fetchFieldValues();

			foreach ( $pageIds as $pageId ) {
				$lastPageId = (int)$pageId;
				yield $lastPageId;
			}
		} while ( count( $pageIds ) === self::BATCH_SIZE );
	}

}
