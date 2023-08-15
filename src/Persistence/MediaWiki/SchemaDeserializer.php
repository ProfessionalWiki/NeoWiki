<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class SchemaDeserializer {

	/**
	 * @throws InvalidArgumentException
	 */
	public function deserialize( SchemaId $schemaId, string $json ): Schema {
		$json = json_decode( $json, true );

		if ( !is_array( $json ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		return new Schema(
			id: $schemaId,
			description: $json['description'] ?? '',
			properties: $this->propertiesFromJson( $json ),
		);
	}

	private function propertiesFromJson( array $json ): PropertyDefinitions {
		$properties = [];

		foreach ( $json['propertyDefinitions'] ?? [] as $propertyName => $property ) {
			if ( !is_string( $propertyName ) ) {
				throw new InvalidArgumentException( 'Property name must be a string' );
			}

			$properties[$propertyName] = $this->propertyDefinitionFromJson( $property, $propertyName );
		}

		return new PropertyDefinitions( $properties );
	}

	private function propertyDefinitionFromJson( array $property, string $propertyName ): PropertyDefinition {
		return match ( ValueType::from( $property['type'] ) ) {
			ValueType::Boolean => new BooleanProperty(
				format: ValueFormat::from( $property['format'] ),
				description: $property['description'] ?? '',
				required: $property['required'] ?? false,
				default: $property['default'] ?? null,
			),

			ValueType::Number => new NumberProperty(
				format: ValueFormat::from( $property['format'] ),
				description: $property['description'] ?? '',
				required: $property['required'] ?? false,
				default: $property['default'] ?? null,
				minimum: $property['minimum'] ?? null,
				maximum: $property['maximum'] ?? null,
			),

			ValueType::Relation => new RelationProperty(
				description: $property['description'] ?? '',
				required: $property['required'] ?? false,
				default: $property['default'] ?? null,
				relationType: new RelationType( $property['relation'] ?? $propertyName ),
				targetSchema: new SchemaId( $property['targetSchema'] ?? '__UNDEFINED__' ),
				multiple: $property['multiple'] ?? false,
			),

			ValueType::String => new StringProperty(
				format: ValueFormat::from( $property['format'] ),
				description: $property['description'] ?? '',
				required: $property['required'] ?? false,
				default: $property['default'] ?? null,
				multiple: $property['multiple'] ?? false,
			),
		};
	}

}
