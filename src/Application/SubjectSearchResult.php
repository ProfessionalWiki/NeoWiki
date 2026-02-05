<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

class SubjectSearchResult {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
	) {
	}

}
