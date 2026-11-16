<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * A Violation of one Subject, with the Schema the Subject was validated against and, for a Violation
 * about a relation value part, the Subject that relation points to.
 */
readonly class SubjectViolation {

	public function __construct(
		public SubjectId $subjectId,
		public string $schemaName,
		public Violation $violation,
		public ?SubjectId $relationTarget,
	) {
	}

}
