<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

/**
 * The Subjects whose data was edited last, newest first. Adding a Subject counts as editing it.
 *
 * Each Subject is listed once, at its last edit, with the data its page publishes. A Subject on a page the caller may
 * not read is left out as if absent.
 */
interface RecentlyEditedSubjectLookup {

	/**
	 * @return list<SubjectSummary> At most $limit, and fewer when the lookup's bounds end the list early.
	 */
	public function getRecentlyEditedSubjects( int $limit ): array;

}
