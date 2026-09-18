<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * Which Subject a search hit matched and through what, for the reader who is looking at a page in a
 * result list and cannot see why it is there.
 */
readonly class SubjectSearchMatch {

	/**
	 * @param ?string $subjectName The Subject's name, or null where the row names it already: as the
	 *   text of its link, as the title of the page the row links to, or as a matched label below
	 * @param MatchedSearchLine[] $lines
	 */
	public function __construct(
		public string $schemaName,
		public ?string $subjectName,
		public array $lines
	) {
	}

}
