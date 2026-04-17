<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;

/**
 * Walks a write-path patch array and resolves every select statement value
 * (id, label, or {id, label} object) to the canonical live option ID.
 *
 * Leaves non-select entries, deletions (null), and properties not found
 * on the Schema untouched.
 */
readonly class SelectPatchResolver {

	public function __construct(
		private SelectValueResolver $valueResolver,
	) {
	}

	/**
	 * @param array<string, mixed> $patch
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When a select value cannot be resolved.
	 */
	public function resolve( Schema $schema, array $patch ): array {
		foreach ( $patch as $propertyName => $entry ) {
			if ( !is_array( $entry ) ) {
				continue;
			}

			if ( ( $entry['propertyType'] ?? null ) !== SelectType::NAME ) {
				continue;
			}

			if ( !array_key_exists( 'value', $entry ) ) {
				continue;
			}

			if ( !$schema->hasProperty( $propertyName ) ) {
				continue;
			}

			$property = $schema->getProperty( $propertyName );

			if ( !$property instanceof SelectProperty ) {
				continue;
			}

			$entry['value'] = $this->resolveEntryValue(
				(string)$propertyName,
				$property,
				$entry['value']
			);

			$patch[$propertyName] = $entry;
		}

		return $patch;
	}

	private function resolveEntryValue( string $propertyName, SelectProperty $property, mixed $value ): mixed {
		if ( $this->isListOfValues( $value ) ) {
			return array_map(
				fn( mixed $item ): string => $this->resolveSingle( $propertyName, $property, $item ),
				$value
			);
		}

		return $this->resolveSingle( $propertyName, $property, $value );
	}

	private function resolveSingle( string $propertyName, SelectProperty $property, mixed $value ): string {
		if ( !is_string( $value ) && !is_array( $value ) ) {
			throw new InvalidArgumentException(
				"Select value for \"{$propertyName}\" must be a string or object"
			);
		}

		try {
			return $this->valueResolver->resolve( $property, $value );
		} catch ( InvalidArgumentException $e ) {
			throw new InvalidArgumentException(
				"Invalid select value for \"{$propertyName}\": " . $e->getMessage(),
				0,
				$e
			);
		}
	}

	private function isListOfValues( mixed $value ): bool {
		return is_array( $value ) && array_is_list( $value );
	}

}
