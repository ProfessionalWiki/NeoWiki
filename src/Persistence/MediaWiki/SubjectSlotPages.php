<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * The pages whose current revision has a subject slot. Every other page holds no Subjects.
 */
class SubjectSlotPages {

	/**
	 * Selects their page_id. The caller is left unset, so that embedding this as a subquery marks it as one
	 * rather than reporting it as a query of its own.
	 */
	public static function newQuery( IReadableDatabase $db ): SelectQueryBuilder {
		return $db->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->join( 'slots', null, 'slot_revision_id = page_latest' )
			->join( 'slot_roles', null, 'slot_role_id = role_id' )
			->where( [ 'role_name' => MediaWikiSubjectRepository::SLOT_NAME ] );
	}

	/**
	 * @return int[] The next batch of their ids, in order
	 */
	public static function pageIdsAfter( IReadableDatabase $db, int $afterPageId, int $limit ): array {
		return array_map( 'intval', self::newQuery( $db )
			->where( $db->buildComparison( '>', [ 'page_id' => $afterPageId ] ) )
			->orderBy( 'page_id' )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

}
