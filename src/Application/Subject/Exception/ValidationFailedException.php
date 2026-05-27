<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Subject\Exception;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use RuntimeException;

class ValidationFailedException extends RuntimeException {

	/** @param Violation[] $violations */
	public function __construct(
		public readonly array $violations,
		string $message = 'Validation failed'
	) {
		parent::__construct( $message );
	}

}
