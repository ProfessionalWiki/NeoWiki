<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * One of a Subject's searchable strings that the search matched, and where in it the match sits.
 */
readonly class MatchedSearchLine {

	/**
	 * @param ?string $propertyName The property holding the value, or null for the Subject's label
	 * @param string[] $parts The line split the way {@see SearchTermMatcher::splitOnMatches} splits it
	 */
	public function __construct(
		public ?string $propertyName,
		public array $parts
	) {
	}

}
