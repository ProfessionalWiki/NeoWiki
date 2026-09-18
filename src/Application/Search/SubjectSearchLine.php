<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * One searchable string a Subject contributes to the search index, and where in the Subject it came
 * from: a Statement's value carries the name of the property holding it, a label carries none.
 */
readonly class SubjectSearchLine {

	/**
	 * @param ?string $propertyName The property holding the value, or null for the Subject's label
	 */
	public function __construct(
		public ?string $propertyName,
		public string $text
	) {
	}

}
