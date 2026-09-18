<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

/**
 * A {@see PropertyType} that accepts input shapes beyond the one it stores, and canonicalizes them.
 *
 * Optional: a type that does not implement it is handed its input unchanged.
 */
interface NormalizesRawValue {

	/**
	 * Canonicalize one Statement's raw input value, before it becomes a NeoValue.
	 *
	 * `$raw` is whatever the caller sent and has not been shape-checked. Never throw: report a part
	 * you cannot canonicalize through {@see NormalizationResult::unresolvable()}, because the same
	 * call serves writes, which refuse, and dry-run validation, which keeps the value. The Violation's
	 * `args` must be strings or numbers: they become the message's parameters.
	 */
	public function normalizeRawValue( mixed $raw, PropertyDefinition $definition ): NormalizationResult;

}
