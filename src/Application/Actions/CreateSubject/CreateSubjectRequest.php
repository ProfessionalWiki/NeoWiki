<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

readonly class CreateSubjectRequest {

	public function __construct(
		public int $pageId,
		public bool $isMainSubject,

		public string $label,

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
