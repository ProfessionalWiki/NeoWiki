<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\DeletedPageIdsLookup;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class DatabaseDeletedPageIdsLookup implements DeletedPageIdsLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
	) {
	}

	/**
	 * @return int[]
	 */
	public function getDeletedPageIdsAfter( int $afterPageId, int $limit ): array {
		return array_map( 'intval', $this->newDeletedPageQuery()
			->select( 'ar_page_id' )
			->distinct()
			->where( $this->db->buildComparison( '>', [ 'ar_page_id' => $afterPageId ] ) )
			->orderBy( 'ar_page_id' )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	public function countDeletedPages(): int {
		$count = $this->newDeletedPageQuery()
			->select( 'COUNT(DISTINCT ar_page_id)' )
			->caller( __METHOD__ )
			->fetchField();

		return is_numeric( $count ) ? (int)$count : 0;
	}

	/**
	 * The archived revisions whose page id has no row in the page table: the pages the wiki no longer
	 * has. Ancient wikis' archive rows predate ar_page_id, so those are excluded.
	 */
	private function newDeletedPageQuery(): SelectQueryBuilder {
		return $this->db->newSelectQueryBuilder()
			->from( 'archive' )
			->leftJoin( 'page', null, 'page_id = ar_page_id' )
			->where( [
				'page_id' => null,
				$this->db->expr( 'ar_page_id', '!=', null ),
			] );
	}

}
