<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage;

readonly class CreateSubjectPageRequest {

	public function __construct(
		/**
		 * Null when the caller named no Subject. Whitespace counts as nothing. A label that is a
		 * main-namespace page title also titles the page created.
		 */
		public ?string $label,

		public string $schemaName,

		/**
		 * @var array<string, mixed[]>
		 */
		public array $statements,

		public ?string $comment = null,
	) {
	}

}
