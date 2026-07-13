<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use ProfessionalWiki\NeoWiki\Persistence\DeletedSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Wikimedia\Rdbms\IReadableDatabase;

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
	 * @return int[]
	 */
	public function getDeletedSubjectPageIds(): array {
		$roleId = $this->getSubjectSlotRoleId();

		if ( $roleId === null ) {
			return [];
		}

		$pageIds = $this->db->newSelectQueryBuilder()
			->select( 'ar_page_id' )
			->distinct()
			->from( 'archive' )
			->join( 'slots', null, 'slot_revision_id = ar_rev_id' )
			->leftJoin( 'page', null, 'page_id = ar_page_id' )
			->where( [
				'slot_role_id' => $roleId,
				'page_id' => null,
			] )
			->orderBy( 'ar_page_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $pageIds );
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
