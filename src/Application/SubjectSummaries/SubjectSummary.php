<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

/**
 * One row of a Subject listing. $displayName and $displayNameIsGenerated mean what they mean in
 * GetSubjectResponseItem: the chosen name, else the Schema name, and whether nobody chose one.
 */
readonly class SubjectSummary {

	public function __construct(
		public string $subjectId,
		public string $displayName,
		public bool $displayNameIsGenerated,
		public string $schemaName,
		public int $pageId,
		public string $pageTitle,
		/** ISO 8601 in UTC: the last edit of the page holding the Subject. */
		public string $lastEdited,
	) {
	}

}
