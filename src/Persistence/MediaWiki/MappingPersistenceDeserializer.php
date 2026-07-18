<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

class MappingPersistenceDeserializer {

	/**
	 * @throws InvalidArgumentException When the JSON is not a Mapping document (invalid JSON, or no
	 *   `schemas` object). The lookups catch this and treat the page as having no valid Mapping, so a
	 *   malformed page never breaks a projection. A single malformed Schema entry is skipped rather than
	 *   failing the whole page, mirroring how a malformed property entry is skipped.
	 */
	public function deserialize( MappingName $name, string $json ): Mapping {
		$data = json_decode( $json, true );

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		if ( !is_array( $data['schemas'] ?? null ) ) {
			throw new InvalidArgumentException( 'Mapping is missing required fields' );
		}

		return new Mapping(
			name: $name,
			prefixes: $this->prefixesFromJson( $data ),
			schemas: $this->schemasFromJson( $data['schemas'] ),
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
	 * @param array<mixed> $schemas
	 * @return array<string, SchemaMapping>
	 */
	private function schemasFromJson( array $schemas ): array {
		$mappings = [];

		foreach ( $schemas as $schemaName => $entry ) {
			$schemaMapping = $this->schemaMappingFromJson( (string)$schemaName, $entry );

			if ( $schemaMapping !== null ) {
				$mappings[(string)$schemaName] = $schemaMapping;
			}
		}

		return $mappings;
	}

	private function schemaMappingFromJson( string $schemaName, mixed $entry ): ?SchemaMapping {
		if ( !is_array( $entry ) ) {
			return null;
		}

		$subject = $entry['subject'] ?? null;
		$class = is_array( $subject ) ? ( $subject['class'] ?? null ) : null;

		if ( !is_string( $class ) ) {
			return null;
		}

		try {
			$schema = new SchemaName( $schemaName );
		} catch ( InvalidArgumentException ) {
			return null;
		}

		return new SchemaMapping(
			schema: $schema,
			subjectClass: $class,
			properties: $this->propertyMappingsFromJson( $entry ),
		);
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private function propertyMappingsFromJson( array $entry ): PropertyMappings {
		$mappings = [];

		foreach ( $this->arrayValue( $entry, 'properties' ) as $name => $property ) {
			if ( is_string( $name ) && is_array( $property ) && is_string( $property['predicate'] ?? null ) ) {
				$mappings[$name] = new PropertyMapping(
					predicate: $property['predicate'],
					language: is_string( $property['lang'] ?? null ) ? $property['lang'] : null,
					datatype: is_string( $property['datatype'] ?? null ) ? $property['datatype'] : null,
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
