<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

use ProfessionalWiki\NeoWiki\Application\HostSubject;

/**
 * One row of a Subject listing. $displayName, $displayNameIsGenerated and $hostSubject mean what they mean in
 * GetSubjectResponseItem: the chosen name, else the Schema name, whether nobody chose one, and the Host Subject of
 * a Dependent Subject nobody named.
 */
readonly class SubjectSummary {

	public function __construct(
		public string $subjectId,
		public string $displayName,
		public bool $displayNameIsGenerated,
		public string $schemaName,
		public int $pageId,
		public string $pageTitle,
		/** ISO 8601 in UTC: when the Subject was last edited, which SubjectSummaryLookup takes from the page's last edit. */
		public string $lastEdited,
		public ?HostSubject $hostSubject,
	) {
	}

}
