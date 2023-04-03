<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

class GetSubjectResponse {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		/**
		 * @var string[]
		 */
		public readonly array $types,
		/**
		 * @var array<string, mixed>
		 */
		public readonly array $properties
	) {
	}

}
