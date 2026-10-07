<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizesRawValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;

/**
 * Canonicalizes the raw values in an incoming statements array, by asking each one's Property Type.
 *
 * The walk knows nothing about any particular type: a type that accepts input shapes beyond the one
 * it stores says so by implementing {@see NormalizesRawValue}, and every other entry is passed
 * through.
 */
readonly class StatementNormalizer {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When a value cannot be stored, canonicalized or as sent.
	 */
	public function normalize( ?Schema $schema, array $statements ): array {
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

			$entry['value'] = $this->normalizeValue( $type, $name, $entry['value'], $definition );
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

	/**
	 * @throws InvalidArgumentException
	 */
	private function normalizeValue(
		NormalizesRawValue $type,
		string $propertyName,
		mixed $raw,
		PropertyDefinition $definition
	): mixed {
		try {
			return $type->normalizeRawValue( $raw, $definition );
		} catch ( InvalidArgumentException $e ) {
			throw new InvalidArgumentException(
				"Value of \"{$propertyName}\" does not fit property type \"{$definition->getPropertyType()}\": {$e->getMessage()}",
				0,
				$e
			);
		}
	}

}
