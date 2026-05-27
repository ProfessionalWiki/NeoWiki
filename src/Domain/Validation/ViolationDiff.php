<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

final class ViolationDiff {

	/**
	 * @param Violation[] $proposed
	 * @param Violation[] $prior
	 *
	 * @return Violation[] Violations present in $proposed but not in $prior,
	 *                    identified by (propertyName, code).
	 */
	public static function newViolations( array $proposed, array $prior ): array {
		$priorKeys = [];
		foreach ( $prior as $violation ) {
			$priorKeys[ self::key( $violation ) ] = true;
		}

		$new = [];
		foreach ( $proposed as $violation ) {
			if ( !isset( $priorKeys[ self::key( $violation ) ] ) ) {
				$new[] = $violation;
			}
		}

		return $new;
	}

	private static function key( Violation $v ): string {
		return ( $v->propertyName?->text ?? '' ) . "\0" . $v->code;
	}

}
