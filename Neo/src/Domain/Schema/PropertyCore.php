<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

class PropertyCore {

	/**
	 * A null default means there is no default.
	 */
	public function __construct(
		public readonly string $description,
		public readonly bool $required,
		public readonly mixed $default,
	) {
	}

}
