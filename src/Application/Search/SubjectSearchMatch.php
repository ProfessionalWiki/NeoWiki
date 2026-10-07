<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * Which Subject a search hit matched, and through which lines.
 */
readonly class SubjectSearchMatch {

	/**
	 * @param ?string $subjectName Null where the row already names the Subject: as its link text, its
	 *   page title, or a matched label
	 * @param MatchedSearchLine[] $lines
	 */
	public function __construct(
		public string $schemaName,
		public ?string $subjectName,
		public array $lines
	) {
	}

}
