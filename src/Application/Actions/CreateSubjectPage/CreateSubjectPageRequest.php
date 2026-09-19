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

		/**
		 * The title the caller chose for the page created. Null, empty or blank leaves the title to
		 * the label; a title that titles no page here is refused rather than replaced.
		 */
		public ?string $pageTitle,

		public string $schemaName,

		/**
		 * @var array<string, mixed[]>
		 */
		public array $statements,

		public ?string $comment = null,

		/**
		 * Client-supplied Subject ID. Must be well-formed and unused; when null the server mints one.
		 */
		public ?string $id = null,
	) {
	}

}
