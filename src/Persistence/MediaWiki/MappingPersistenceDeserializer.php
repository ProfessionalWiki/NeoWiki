<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\NodeScope;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\PropertyMappings;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SchemaMapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\SubjectMapping;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

class MappingPersistenceDeserializer {

	/**
	 * @throws InvalidArgumentException When the JSON is not a Mapping document of the supported format
	 *   version (invalid JSON, a `version` other than the current one, or no `schemas` object). The
	 *   lookups catch this and treat the page as having no valid Mapping, so an unreadable page degrades
	 *   to an unknown projection instead of breaking a projection or a page save. A single malformed
	 *   Schema entry is skipped rather than failing the whole page, mirroring how a malformed property
	 *   entry is skipped.
	 */
	public function deserialize( MappingName $name, string $json ): Mapping {
		$data = json_decode( $json, true );

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		if ( ( $data['version'] ?? null ) !== Mapping::FORMAT_VERSION ) {
			throw new InvalidArgumentException( 'Unsupported mapping format version' );
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

		try {
			// The key is the Schema name; constructing it validates the key — an empty or reserved name
			// throws — so a malformed key skips the entry. The result is not stored: the entry's Schema is
			// its key in Mapping::$schemas.
			new SchemaName( $schemaName );
		} catch ( InvalidArgumentException ) {
			return null;
		}

		$subject = $this->subjectMappingFromJson( $entry );
		$contributions = $this->contributionsFromJson( $entry );

		// An entry that neither projects its own Subject nor contributes to another has nothing to say.
		if ( $subject === null && $contributions === [] ) {
			return null;
		}

		return new SchemaMapping(
			subject: $subject,
			properties: $this->propertyMappingsFromJson( $this->arrayValue( $entry, 'properties' ) ),
			nodes: $this->nodeMappingsFromJson( $entry ),
			contributions: $contributions,
		);
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	private function subjectMappingFromJson( array $entry ): ?SubjectMapping {
		$subject = $this->arrayValue( $entry, 'subject' );
		$class = $subject['class'] ?? null;

		if ( !is_string( $class ) ) {
			return null;
		}

		return new SubjectMapping(
			class: $class,
			labelPredicate: $this->stringOrNull( $subject['labelPredicate'] ?? null ),
		);
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, NodeMapping>
	 */
	private function nodeMappingsFromJson( array $entry ): array {
		$nodes = [];

		foreach ( $this->arrayValue( $entry, 'nodes' ) as $key => $node ) {
			if ( !is_array( $node ) || !is_string( $node['class'] ?? null ) || !is_string( $node['linkPredicate'] ?? null ) ) {
				continue;
			}

			$nodes[(string)$key] = new NodeMapping(
				class: $node['class'],
				linkPredicate: $node['linkPredicate'],
				parent: $this->stringOrNull( $node['parent'] ?? null ),
				scope: NodeScope::tryFrom( (string)( $node['per'] ?? '' ) ) ?? NodeScope::Subject,
			);
		}

		return $nodes;
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, PropertyMappings> Keyed by relation name.
	 */
	private function contributionsFromJson( array $entry ): array {
		$contributions = [];

		foreach ( $this->arrayValue( $entry, 'contributions' ) as $relation => $properties ) {
			$relation = trim( (string)$relation );

			if ( $relation === '' || !is_array( $properties ) ) {
				continue;
			}

			$propertyMappings = $this->propertyMappingsFromJson( $properties );

			if ( $propertyMappings->asArray() !== [] ) {
				$contributions[$relation] = $propertyMappings;
			}
		}

		return $contributions;
	}

	/**
	 * @param array<mixed> $properties
	 */
	private function propertyMappingsFromJson( array $properties ): PropertyMappings {
		$mappings = [];

		foreach ( $properties as $name => $property ) {
			if ( is_array( $property ) && is_string( $property['predicate'] ?? null ) ) {
				$mappings[(string)$name] = new PropertyMapping(
					predicate: $property['predicate'],
					language: $this->stringOrNull( $property['lang'] ?? null ),
					datatype: $this->stringOrNull( $property['datatype'] ?? null ),
					node: $this->stringOrNull( $property['node'] ?? null ),
				);
			}
		}

		return new PropertyMappings( $mappings );
	}

	private function stringOrNull( mixed $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<mixed>
	 */
	private function arrayValue( array $data, string $key ): array {
		return is_array( $data[$key] ?? null ) ? $data[$key] : [];
	}

}
