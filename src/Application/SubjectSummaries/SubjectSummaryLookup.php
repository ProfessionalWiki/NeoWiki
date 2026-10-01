<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

/**
 * Lists this wiki's Subjects a page at a time, without totals (ADR 27, #1062).
 *
 * A Subject on a page the caller may not read is left out as if absent. Search matches the chosen name or the page
 * title containing the text, case-insensitively, or a Subject id starting with it. Ties in any order are broken by
 * Subject id descending; generated names sort after chosen names in both directions.
 */
interface SubjectSummaryLookup {

	public function getSubjectSummaries( SubjectSummaryQuery $query ): SubjectSummaries;

}
