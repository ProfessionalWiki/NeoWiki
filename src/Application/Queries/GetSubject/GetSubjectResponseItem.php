<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

readonly class GetSubjectResponseItem {

	public function __construct(
		public string $id,
		public string $label,
		public string $schemaId,
		/**
		 * @var array<string, mixed>
		 */
		public array $statements,
		public ?int $pageId,
		public ?string $pageTitle,
	) {
	}

}
