<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain;

class SubjectProperties {

	public function __construct(
		/**
		 * @var array<string, string|int|array<int|string>>
		 */
		public readonly array $map,
	) {
	}

}
