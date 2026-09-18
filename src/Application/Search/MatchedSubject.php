<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

/**
 * The Subject a search hit was traced back to, and the lines of it that the search matched.
 */
readonly class MatchedSubject {

	/**
	 * @param MatchedSearchLine[] $lines
	 */
	public function __construct(
		public Subject $subject,
		public array $lines
	) {
	}

}
