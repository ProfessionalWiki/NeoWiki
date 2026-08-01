<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIdsLookup;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class DatabaseSubjectPageIdsLookup implements SubjectPageIdsLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
		private readonly NameTableStore $slotRoleStore,
	) {
	}

	/**
	 * A page carries a Subject when its latest revision has a row in the subject slot role, which is
	 * what joining `slots` on `page_latest` selects.
	 *
	 * @return int[]
	 */
	public function getSubjectPageIdsAfter( int $afterPageId, int $limit ): array {
		$roleId = $this->getSubjectSlotRoleId();

		if ( $roleId === null ) {
			return [];
		}

		$pageIds = $this->newSubjectPageQuery( $roleId )
			->select( 'page_id' )
			->where( $this->db->expr( 'page_id', '>', $afterPageId ) )
			->orderBy( 'page_id' )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $pageIds );
	}

	public function countSubjectPages(): int {
		$roleId = $this->getSubjectSlotRoleId();

		if ( $roleId === null ) {
			return 0;
		}

		return $this->newSubjectPageQuery( $roleId )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	private function newSubjectPageQuery( int $roleId ): SelectQueryBuilder {
		return $this->db->newSelectQueryBuilder()
			->from( 'page' )
			->join( 'slots', null, 'slot_revision_id = page_latest' )
			->where( [ 'slot_role_id' => $roleId ] );
	}

	/**
	 * A wiki that has never stored a Subject has no subject slot role, and therefore no subject pages.
	 */
	private function getSubjectSlotRoleId(): ?int {
		try {
			return $this->slotRoleStore->getId( MediaWikiSubjectRepository::SLOT_NAME );
		} catch ( NameTableAccessException ) {
			return null;
		}
	}

}
