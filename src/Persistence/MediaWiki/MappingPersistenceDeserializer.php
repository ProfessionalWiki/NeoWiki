<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

class MappingPersistenceDeserializer {

	/**
	 * @throws InvalidArgumentException When the JSON is missing the fields a Mapping needs. The lookups
	 *   catch this and treat the page as having no valid Mapping, so a malformed page never breaks a
	 *   projection.
	 */
	public function deserialize( MappingName $name, string $json ): Mapping {
		$data = json_decode( $json, true );

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		$schema = $data['schema'] ?? null;
		$target = $data['target'] ?? null;
		$class = $data['subject']['class'] ?? null;

		if ( !is_string( $schema ) || !is_string( $target ) || !is_string( $class ) ) {
			throw new InvalidArgumentException( 'Mapping is missing required fields' );
		}

		return new Mapping(
			name: $name,
			schema: new SchemaName( $schema ),
			target: $target,
			prefixes: $this->prefixesFromJson( $data ),
			subjectClass: $class,
			properties: $this->propertyMappingsFromJson( $data ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function prefixesFromJson( array $data ): array {
		$prefixes = [];

		foreach ( $this->arrayValue( $data, 'prefixes' ) as $label => $namespace ) {
			if ( is_string( $label ) && is_string( $namespace ) ) {
				$prefixes[$label] = $namespace;
			}
		}

		return $prefixes;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function propertyMappingsFromJson( array $data ): PropertyMappings {
		$mappings = [];

		foreach ( $this->arrayValue( $data, 'properties' ) as $name => $entry ) {
			if ( is_string( $name ) && is_array( $entry ) && is_string( $entry['predicate'] ?? null ) ) {
				$mappings[$name] = new PropertyMapping(
					predicate: $entry['predicate'],
					language: is_string( $entry['lang'] ?? null ) ? $entry['lang'] : null,
					datatype: is_string( $entry['datatype'] ?? null ) ? $entry['datatype'] : null,
				);
			}
		}

		return new PropertyMappings( $mappings );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<mixed>
	 */
	private function arrayValue( array $data, string $key ): array {
		return is_array( $data[$key] ?? null ) ? $data[$key] : [];
	}

}
