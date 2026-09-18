<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * The Subject a search hit should take the reader to instead of the page holding it.
 */
readonly class SubjectSearchLanding {

	/**
	 * @param bool $subjectNameIsGenerated Whether the name stands in for one nobody chose, which
	 *   every surface showing such a name marks as such
	 */
	public function __construct(
		public SubjectId $subjectId,
		public string $subjectName,
		public bool $subjectNameIsGenerated
	) {
	}

}
