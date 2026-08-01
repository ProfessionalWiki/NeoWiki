<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

/**
 * The pages a graph rebuild reprojects: every page whose latest revision carries a Subject.
 *
 * Enumeration is paged rather than returned whole, so a rebuild's memory does not scale with the wiki.
 */
interface SubjectPageIdsLookup {

	/**
	 * The next $limit subject page ids after $afterPageId, in ascending page id order. Paging by page id
	 * rather than by offset means a page created or deleted mid-rebuild cannot make the walk skip
	 * another one.
	 *
	 * @return int[]
	 */
	public function getSubjectPageIdsAfter( int $afterPageId, int $limit ): array;

	/**
	 * How many pages carry a Subject, for reporting progress against.
	 */
	public function countSubjectPages(): int;

}
