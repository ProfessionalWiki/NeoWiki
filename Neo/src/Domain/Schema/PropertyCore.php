<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

readonly class PropertyCore {

	/**
	 * A null default means there is no default.
	 */
	public function __construct(
		public string $description,
		public bool $required,
		public mixed $default,
	) {
	}

}
