<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

readonly class GetPageSubjectsResponse {

	public function __construct(
		public int $pageId,
		public ?string $mainSubjectId,
		/**
		 * @var array<string, GetSubjectResponseItem> Indexed by subject ID, main first then children
		 */
		public array $subjects,
	) {
	}

}
