<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use InvalidArgumentException;

readonly class SubjectLabel {

	public function __construct(
		public string $text,
	) {
		if ( trim( $text ) === '' ) {
			throw new InvalidArgumentException( 'SubjectLabel cannot be empty' );
		}
	}

}
