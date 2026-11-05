<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubjectUpdate;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

readonly class SubjectUpdateViolations {

	/**
	 * @param Violation[] $all What the updated Subject violates.
	 * @param Violation[] $introduced Those among $all that the stored Subject does not violate.
	 */
	public function __construct(
		public array $all,
		public array $introduced,
	) {
	}

	public function isIntroduced( Violation $violation ): bool {
		return in_array( $violation, $this->introduced, true );
	}

}
