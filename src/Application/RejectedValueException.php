<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * Thrown by a write path for a value a Property Type could not canonicalize.
 *
 * An InvalidArgumentException so that every entry point catching one to answer 400 keeps doing so
 * unchanged; entry points that catch this class first answer with the Violation it carries.
 */
class RejectedValueException extends InvalidArgumentException {

	public function __construct(
		public readonly Violation $violation,
	) {
		parent::__construct( self::describe( $violation ) );
	}

	private static function describe( Violation $violation ): string {
		$text = $violation->code;

		if ( $violation->args !== [] ) {
			$text .= ': ' . implode( ', ', array_map( strval( ... ), $violation->args ) );
		}

		if ( $violation->propertyName !== null ) {
			$text .= ' on ' . $violation->propertyName->text;
		}

		if ( $violation->valuePartIndex !== null ) {
			$text .= '[' . $violation->valuePartIndex . ']';
		}

		return $text;
	}

}
