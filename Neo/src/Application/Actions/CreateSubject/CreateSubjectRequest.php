<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

class CreateSubjectRequest {

	public function __construct(
		public readonly int $pageId,
		public readonly bool $isMainSubject,

		public readonly string $label,

		/**
		 * @var string[]
		 */
		public readonly array $types,

		/**
		 * @var array<string, mixed[]>
		 */
		public readonly array $properties,
	) {
	}

}
