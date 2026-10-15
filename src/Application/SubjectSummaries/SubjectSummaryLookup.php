<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

/**
 * Lists this wiki's Subjects a page at a time, without totals (ADR 27, #1062).
 *
 * A Subject on a page the caller may not read is left out as if absent, and so is a Dependent Subject unless its
 * Schema is the one listed. Search matches the chosen name or the page title containing the text, case-insensitively,
 * or a Subject id starting with it. Ties in any order are broken by Subject id in the direction of the sort; generated
 * names sort after chosen names in both directions.
 */
interface SubjectSummaryLookup {

	/**
	 * @throws SubjectSummarySearchTimeoutException
	 */
	public function getSubjectSummaries( SubjectSummaryQuery $query ): SubjectSummaries;

}
