<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

readonly class CreateSubjectRequest {

	public function __construct(
		public int $pageId,
		public bool $isMainSubject,

		public string $label,

		public string $schemaId,

		/**
		 * @var array<string, mixed[]>
		 */
		public array $statements,
	) {
	}

}
