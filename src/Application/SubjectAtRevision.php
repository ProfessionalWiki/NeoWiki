<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

/**
 * A Subject as one revision of its page holds it, beside the page's other Subjects.
 */
readonly class SubjectAtRevision {

	public function __construct(
		public Subject $subject,
		public PageSubjects $pageSubjects,
		public int $revisionId,
	) {
	}

}
