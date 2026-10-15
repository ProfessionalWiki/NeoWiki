<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Keeps each Subject's row of the lowest page id holding it, the page its id resolves to. One id on several pages
 * is what cross-wiki transfer leaves.
 */
class LowestPageRows {

	/**
	 * @param string $alias The alias the query gives `neowiki_subject`
	 */
	public static function condition( IReadableDatabase $db, string $alias ): string {
		$rowOnALowerPage = $db->newSelectQueryBuilder()
			->select( '1' )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE, 'lower_page_row' )
			->where( "lower_page_row.nws_subject_id = $alias.nws_subject_id" )
			->andWhere( "lower_page_row.nws_page_id < $alias.nws_page_id" )
			->getSQL();

		return "NOT EXISTS ($rowOnALowerPage)";
	}

}
