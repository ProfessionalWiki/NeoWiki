<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

/**
 * The pages a graph rebuild removes from a store: those that once carried Subjects and no longer exist
 * in MediaWiki, and so should not be present in any graph database either.
 *
 * Enumeration is paged like {@see SubjectPageIdsLookup}, so a rebuild records how far through this
 * phase it got and continues there rather than starting the phase over.
 */
interface DeletedSubjectPageIdsLookup {

	/**
	 * The next $limit deleted subject page ids after $afterPageId, in ascending page id order.
	 *
	 * @return int[]
	 */
	public function getDeletedSubjectPageIdsAfter( int $afterPageId, int $limit ): array;

	/**
	 * How many pages MediaWiki no longer has once carried Subjects, for reporting progress against.
	 */
	public function countDeletedSubjectPages(): int;

}
