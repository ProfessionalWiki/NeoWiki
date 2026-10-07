<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use InvalidArgumentException;
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
	 * `$raw` is whatever the caller sent and has not been shape-checked. Return a value that names
	 * nothing the definition knows as it was sent, so that {@see PropertyType::validate()} reports it
	 * at the severity the Schema sets: the same call serves writes and dry-run validation.
	 *
	 * @throws InvalidArgumentException When `$raw` cannot be stored, canonicalized or as sent.
	 */
	public function normalizeRawValue( mixed $raw, PropertyDefinition $definition ): mixed;

}
