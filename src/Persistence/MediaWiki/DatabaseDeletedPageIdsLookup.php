<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\DeletedPageIdsLookup;
use Wikimedia\Rdbms\IReadableDatabase;

class DatabaseDeletedPageIdsLookup implements DeletedPageIdsLookup {

	public const int BATCH_SIZE = 500;

	public function __construct(
		private readonly IReadableDatabase $db,
	) {
	}

	/**
	 * Deleting a page moves its revisions to the archive table and removes its page row, so the pages the
	 * wiki no longer has are exactly the archived revisions whose page id has no row in the page table.
	 * An undeleted page reappears in the page table and drops back out of this set.
	 *
	 * Keyset pagination over ar_id, the archive table's primary key. The archive has no index on
	 * ar_page_id, so paging over the page id would sort the whole table for every batch.
	 *
	 * One row per archived revision means a page with several archived revisions is yielded several
	 * times. Consecutively repeated ids are dropped, which covers the usual case of one deletion
	 * archiving a page's revisions together; the rest are left to the caller, for which removing an
	 * already absent page from the graph is a no-op.
	 *
	 * @return iterable<int>
	 */
	public function getDeletedPageIds(): iterable {
		$lastArchiveId = 0;
		$lastPageId = null;

		do {
			$rows = $this->db->newSelectQueryBuilder()
				->select( [ 'ar_id', 'ar_page_id' ] )
				->from( 'archive' )
				->leftJoin( 'page', null, 'page_id = ar_page_id' )
				->where( [
					'page_id' => null,
					// Excludes the archive rows of ancient wikis that predate ar_page_id.
					$this->db->expr( 'ar_page_id', '!=', null ),
					$this->db->expr( 'ar_id', '>', $lastArchiveId ),
				] )
				->orderBy( 'ar_id' )
				->limit( self::BATCH_SIZE )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $rows as $row ) {
				$lastArchiveId = (int)$row->ar_id;
				$pageId = (int)$row->ar_page_id;

				if ( $pageId !== $lastPageId ) {
					$lastPageId = $pageId;
					yield $pageId;
				}
			}
		} while ( $rows->numRows() === self::BATCH_SIZE );
	}

}
