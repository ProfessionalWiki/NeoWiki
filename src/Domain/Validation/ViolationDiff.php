<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

class ViolationDiff {

	/**
	 * Returns violations in $proposed that are not present in $prior, identified
	 * by (propertyName, code). Used by Replace-style writes to detect violations
	 * introduced by an edit while ignoring pre-existing ones.
	 *
	 * @param Violation[] $proposed
	 * @param Violation[] $prior
	 * @return Violation[]
	 */
	public static function newViolations( array $proposed, array $prior ): array {
		$priorKeys = [];
		foreach ( $prior as $violation ) {
			$priorKeys[self::key( $violation )] = true;
		}

		$new = [];
		foreach ( $proposed as $violation ) {
			if ( !isset( $priorKeys[self::key( $violation )] ) ) {
				$new[] = $violation;
			}
		}
		return $new;
	}

	private static function key( Violation $violation ): string {
		return ( $violation->propertyName?->text ?? '' ) . "\0" . $violation->code;
	}

}
