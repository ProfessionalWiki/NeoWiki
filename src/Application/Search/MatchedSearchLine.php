<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * A searchable string of a Subject that the search matched.
 */
readonly class MatchedSearchLine {

	/**
	 * @param ?string $propertyName Null for the label
	 * @param string[] $parts The line split the way {@see SearchTermMatcher::splitOnMatches} splits it
	 * @param string[] $relationPropertyNames The relation properties leading to the Dependent Subject the line is from,
	 *   outermost first; empty for a line of the matched Subject itself
	 */
	public function __construct(
		public ?string $propertyName,
		public array $parts,
		public array $relationPropertyNames
	) {
	}

}
