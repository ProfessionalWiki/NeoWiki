<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

class ViolationDiff {

	/**
	 * Returns violations in $proposed that are not present in $prior. Used by
	 * Replace-style writes to detect violations introduced by an edit while
	 * ignoring pre-existing ones.
	 *
	 * A proposed violation is present in $prior when a prior one has the same
	 * property, code and valuePartIndex, or else when one of the prior
	 * violations left over has the same property and code. Each prior
	 * violation matches one proposed violation at most.
	 *
	 * @param Violation[] $proposed
	 * @param Violation[] $prior
	 * @return Violation[]
	 */
	public static function newViolations( array $proposed, array $prior ): array {
		[ $proposedLeft, $priorLeft ] = self::unmatched( $proposed, $prior, self::partKey( ... ) );

		return self::unmatched( $proposedLeft, $priorLeft, self::codeKey( ... ) )[0];
	}

	/**
	 * @param Violation[] $proposed
	 * @param Violation[] $prior
	 * @param callable(Violation): string $key
	 * @return array{0: Violation[], 1: Violation[]} The proposed and the prior violations without a match
	 */
	private static function unmatched( array $proposed, array $prior, callable $key ): array {
		$priorByKey = [];
		foreach ( $prior as $violation ) {
			$priorByKey[$key( $violation )][] = $violation;
		}

		$proposedLeft = [];
		foreach ( $proposed as $violation ) {
			if ( ( $priorByKey[$key( $violation )] ?? [] ) === [] ) {
				$proposedLeft[] = $violation;
			} else {
				array_pop( $priorByKey[$key( $violation )] );
			}
		}

		return [ $proposedLeft, array_merge( ...array_values( $priorByKey ) ) ];
	}

	private static function partKey( Violation $violation ): string {
		return self::codeKey( $violation ) . "\0" . ( $violation->valuePartIndex ?? '' );
	}

	private static function codeKey( Violation $violation ): string {
		return ( $violation->propertyName?->text ?? '' ) . "\0" . $violation->code;
	}

}
