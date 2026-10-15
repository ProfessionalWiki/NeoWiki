<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Subject\Exception;

use Exception;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * Thrown whatever the validation enforcement setting. The refused write has changed nothing.
 */
class DependentSubjectRulesViolatedException extends Exception {

	/**
	 * @param Violation[] $violations
	 */
	public function __construct(
		public readonly array $violations,
	) {
		parent::__construct( 'The write breaks the rules for Dependent Subjects' );
	}

}
