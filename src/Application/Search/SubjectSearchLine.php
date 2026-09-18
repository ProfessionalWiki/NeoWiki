<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * One searchable string of a Subject and the property it came from.
 */
readonly class SubjectSearchLine {

	/**
	 * @param ?string $propertyName Null for the label
	 */
	public function __construct(
		public ?string $propertyName,
		public string $text
	) {
	}

}
