<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The Subject a result row leads to instead of its page.
 */
readonly class SubjectSearchLanding {

	/**
	 * @param bool $subjectNameIsGenerated Whether the name is a stand-in the wiki supplied, which the
	 *   row marks as such
	 */
	public function __construct(
		public SubjectId $subjectId,
		public string $subjectName,
		public bool $subjectNameIsGenerated
	) {
	}

}
