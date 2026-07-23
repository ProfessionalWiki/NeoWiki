<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

use InvalidArgumentException;

/**
 * Parses and serializes the two Schema-JSON forms a Constraint can take (ADR 26):
 * a bare scalar/array (default `warning` severity) or an object `{ value, severity }`
 * (booleans drop `value` and imply `true`; `options` carries the array in `value`).
 *
 * A value is treated as object-form iff it is an array carrying a `severity` key. This is
 * intentionally permissive: being type-agnostic is what lets a custom Property Type's own
 * Constraint keys round-trip without any core change.
 *
 * A severity on a key no Violation reads never affects enforcement, but it is not wholly
 * inert: apply() would re-emit that key in object form. Two guards keep that from
 * corrupting non-Constraints — PropertyDefinition::fromJson drops severities on declared
 * Display Attributes, and `schemaContentSchema.json` rejects them on the shape-declaring
 * keys (`multiple`, `relation`, `targetSchema`) at authoring time.
 */
final class SeverityNormalizer {

	/** Core keys handled outside the Constraint model; never severity-bearing. */
	private const RESERVED = [ 'type', 'description', 'default' ];

	/**
	 * @param array<string, mixed> $property
	 * @return array{0: array<string, mixed>, 1: array<string, Severity>} bare values, name->severity
	 */
	public static function extract( array $property ): array {
		$values = $property;
		$severities = [];

		foreach ( $property as $key => $raw ) {
			if ( in_array( $key, self::RESERVED, true ) ) {
				continue;
			}

			if ( is_array( $raw ) && array_key_exists( 'severity', $raw ) ) {
				$severity = is_string( $raw['severity'] ) ? Severity::tryFrom( $raw['severity'] ) : null;

				if ( $severity === null ) {
					throw new InvalidArgumentException( 'Invalid severity: ' . json_encode( $raw['severity'] ) );
				}

				// array_key_exists, not ??: an explicit "value": null must stay null (the
				// boolean object form is the one that legitimately omits the key).
				$values[$key] = array_key_exists( 'value', $raw ) ? $raw['value'] : true;
				$severities[$key] = $severity;
			}
		}

		return [ $values, $severities ];
	}

	/**
	 * Canonical output: re-wrap only `error`-annotated (present) keys; `warning` is the
	 * default and emits as shorthand, so unannotated Schemas round-trip byte-for-byte.
	 *
	 * @param array<string, mixed> $json
	 * @param array<string, Severity> $severities
	 * @return array<string, mixed>
	 */
	public static function apply( array $json, array $severities ): array {
		foreach ( $severities as $key => $severity ) {
			if ( $severity === Severity::Warning || !array_key_exists( $key, $json ) ) {
				continue;
			}

			$json[$key] = is_bool( $json[$key] )
				? [ 'severity' => $severity->value ]
				: [ 'value' => $json[$key], 'severity' => $severity->value ];
		}

		return $json;
	}
}
