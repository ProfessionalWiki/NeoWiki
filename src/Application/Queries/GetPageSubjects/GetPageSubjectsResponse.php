<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

class GetPageSubjectsResponse {

	public function __construct(
		public int $pageId,
		public ?string $mainSubjectId,
		/**
		 * @var array<string, GetSubjectResponseItem> Indexed by subject ID, main first then children
		 */
		public array $subjects,
		/**
		 * @var array<string, GetSubjectResponseItem>|null Subjects targeted by relation values on this page's
		 *      subjects, indexed by subject ID. Null when not requested.
		 */
		public ?array $referencedSubjects = null,
		/**
		 * @var array<string, string>|null Schemas needed to render this page's subjects (and referenced subjects),
		 *      keyed by schema name, value is the JSON-encoded schema. Null when not requested.
		 */
		public ?array $schemas = null,
	) {
	}

}
