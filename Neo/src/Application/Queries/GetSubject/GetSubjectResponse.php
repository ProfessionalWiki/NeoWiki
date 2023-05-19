<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

class GetSubjectResponse {

	public function __construct(
		public readonly string $requestedId,
		/**
		 * @var array<string, GetSubjectResponseItem> Indexed by subject ID
		 */
		public readonly array $subjects,
	) {
	}

}
