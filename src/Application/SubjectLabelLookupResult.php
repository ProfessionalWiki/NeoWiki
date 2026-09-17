<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

class SubjectLabelLookupResult {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		/**
		 * Prefixed title of the page holding the Subject. Nothing makes a label unique, so a caller
		 * choosing between results needs the page to tell namesakes apart.
		 */
		public readonly string $pageTitle,
	) {
	}

}
