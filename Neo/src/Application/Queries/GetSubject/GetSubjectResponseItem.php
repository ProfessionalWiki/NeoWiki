<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

class GetSubjectResponseItem {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $schemaId,
		/**
		 * @var array<string, mixed>
		 */
		public readonly array $properties,
		public readonly ?int $pageId,
		public readonly ?string $pageTitle,
	) {
	}

}
