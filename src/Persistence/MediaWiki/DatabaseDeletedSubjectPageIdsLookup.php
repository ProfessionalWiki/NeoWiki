<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use ProfessionalWiki\NeoWiki\Persistence\DeletedSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class DatabaseDeletedSubjectPageIdsLookup implements DeletedSubjectPageIdsLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
		private readonly NameTableStore $slotRoleStore,
	) {
	}

	/**
	 * Deleting a page moves its revisions to the archive table while leaving their slot rows in
	 * place, so the pages that carried Subjects and are now gone are exactly the archived revisions
	 * with a subject slot whose page has no row in the page table. An undeleted page reappears in
	 * the page table and drops back out of this set.
	 *
	 * A page has one row per archived revision, so the ids are made distinct before they are paged:
	 * without that, a page with more revisions than the batch size would fill every batch with itself.
	 *
	 * @return int[]
	 */
	public function getDeletedSubjectPageIdsAfter( int $afterPageId, int $limit ): array {
		$roleId = $this->getSubjectSlotRoleId();

		if ( $roleId === null ) {
			return [];
		}

		$pageIds = $this->newDeletedPageQuery( $roleId )
			->select( 'ar_page_id' )
			->distinct()
			->where( $this->db->buildComparison( '>', [ 'ar_page_id' => $afterPageId ] ) )
			->orderBy( 'ar_page_id' )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $pageIds );
	}

	public function countDeletedSubjectPages(): int {
		$roleId = $this->getSubjectSlotRoleId();

		if ( $roleId === null ) {
			return 0;
		}

		$count = $this->newDeletedPageQuery( $roleId )
			->select( 'COUNT(DISTINCT ar_page_id)' )
			->caller( __METHOD__ )
			->fetchField();

		return is_numeric( $count ) ? (int)$count : 0;
	}

	private function newDeletedPageQuery( int $roleId ): SelectQueryBuilder {
		return $this->db->newSelectQueryBuilder()
			->from( 'archive' )
			->join( 'slots', null, 'slot_revision_id = ar_rev_id' )
			->leftJoin( 'page', null, 'page_id = ar_page_id' )
			->where( [
				'slot_role_id' => $roleId,
				'page_id' => null,
			] );
	}

	/**
	 * A wiki that has never stored a Subject has no subject slot role, and therefore no deleted
	 * subject pages either.
	 */
	private function getSubjectSlotRoleId(): ?int {
		try {
			return $this->slotRoleStore->getId( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( NameTableAccessException ) {
			return null;
		}
	}

}
