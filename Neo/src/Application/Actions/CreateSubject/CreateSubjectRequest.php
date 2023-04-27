<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

class CreateSubjectRequest {

	public function __construct(
		public readonly int $pageId,
		public readonly bool $isMainSubject,

		public readonly string $label,

		public readonly string $schemaId,

		/**
		 * @var array<string, mixed[]>
		 */
		public readonly array $properties,
	) {
	}

}
