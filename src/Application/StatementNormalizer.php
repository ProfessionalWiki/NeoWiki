<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizesRawValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;

/**
 * Canonicalizes the raw values in an incoming statements array, by asking each one's Property Type.
 *
 * The walk knows nothing about any particular type: a type that accepts input shapes beyond the one
 * it stores says so by implementing {@see NormalizesRawValue}, and every other entry is passed
 * through. What a type cannot canonicalize is reported rather than thrown, so one walk serves both
 * {@see self::normalize()} and {@see self::normalizeOrThrow()}.
 */
readonly class StatementNormalizer {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * Leaves what no type could canonicalize in place, for the validator to report against the
	 * Schema with a per-part index. Used by the dry-run validate endpoints, which answer 200 with
	 * the violations rather than refusing the request.
	 *
	 * @param array<string, mixed> $statements
	 * @return array<string, mixed>
	 */
	public function normalize( ?Schema $schema, array $statements ): array {
		return $this->walk( $schema, $statements, throwOnUnresolvable: false );
	}

	/**
	 * Refuses the whole write on the first value no type could canonicalize. Used by the write
	 * paths, where an uncanonicalized value cannot be stored: it becomes a 400.
	 *
	 * @param array<string, mixed> $statements
	 * @return array<string, mixed>
	 *
	 * @throws RejectedValueException
	 */
	public function normalizeOrThrow( ?Schema $schema, array $statements ): array {
		return $this->walk( $schema, $statements, throwOnUnresolvable: true );
	}

	/**
	 * @param array<string, mixed> $statements
	 * @return array<string, mixed>
	 */
	private function walk( ?Schema $schema, array $statements, bool $throwOnUnresolvable ): array {
		// Without a Schema there is no type to ask: the statements pass through as sent.
		if ( $schema === null ) {
			return $statements;
		}

		foreach ( $statements as $propertyName => $entry ) {
			if ( !is_array( $entry ) || !array_key_exists( 'value', $entry ) ) {
				continue;
			}

			// A decimal-integer property name reaches here as an int, PHP having made it an array key.
			$name = (string)$propertyName;
			$definition = $this->normalizingDefinitionOf( $schema, $name, $entry );

			if ( $definition === null ) {
				continue;
			}

			$type = $this->propertyTypeLookup->getType( $definition->getPropertyType() );

			if ( !$type instanceof NormalizesRawValue ) {
				continue;
			}

			$result = $type->normalizeRawValue( $entry['value'], $definition );

			if ( $result->violation !== null && $throwOnUnresolvable ) {
				throw new RejectedValueException( $result->violation->withPropertyName( new PropertyName( $name ) ) );
			}

			$entry['value'] = $result->value;
			$statements[$propertyName] = $entry;
		}

		return $statements;
	}

	/**
	 * The Property Definition to canonicalize this entry against, or null to pass it through
	 * untouched.
	 *
	 * @param array<string, mixed> $entry
	 */
	private function normalizingDefinitionOf( Schema $schema, string $propertyName, array $entry ): ?PropertyDefinition {
		if ( !$schema->hasProperty( $propertyName ) ) {
			return null;
		}

		$definition = $schema->getProperty( $propertyName );

		// Writer's-schema drift (ADR 11): the caller names the type the property had when it last
		// wrote. Canonicalizing a disagreement against the Schema's current type would rewrite a
		// value the caller never meant as one of that type, so it is left for SubjectValidator to
		// report as a type-mismatch.
		if ( ( $entry['propertyType'] ?? null ) !== $definition->getPropertyType() ) {
			return null;
		}

		return $definition;
	}

}
