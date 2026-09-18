<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * What a {@see NormalizesRawValue} type made of one raw input value.
 *
 * `value` is the best the type could do: every part it could canonicalize is canonical, the rest is
 * the caller's input untouched. `violation` reports the first part it could not: no property name
 * (the caller attaches it, as with {@see PropertyType::validate()}), `valuePartIndex` counted on the
 * value as sent (0 for a value that is not a list), and severity fixed at error, because a value
 * that cannot be stored is not a rule a schema author can relax. A write refuses on `violation`;
 * dry-run validation keeps `value`.
 */
final readonly class NormalizationResult {

	private function __construct(
		public mixed $value,
		public ?Violation $violation,
	) {
	}

	public static function normalized( mixed $value ): self {
		return new self( $value, null );
	}

	/**
	 * @param mixed $value The input, with every part that could be canonicalized already canonical.
	 */
	public static function unresolvable( mixed $value, Violation $violation ): self {
		return new self( $value, $violation );
	}

}
