<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

class ViolationDiff {

	/**
	 * Returns violations in $proposed that are not present in $prior. Used by
	 * Replace-style writes to detect violations introduced by an edit while
	 * ignoring pre-existing ones.
	 *
	 * Identity is (propertyName, code, valuePartIndex). Including
	 * valuePartIndex distinguishes per-value violations on multi-value
	 * properties (e.g. url, select) so that adding a second bad value at a
	 * new index is reported as new, even when prior already reported a
	 * violation with the same code at a different index.
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
		return ( $violation->propertyName?->text ?? '' )
			. "\0" . $violation->code
			. "\0" . ( $violation->valuePartIndex ?? '' );
	}

}
