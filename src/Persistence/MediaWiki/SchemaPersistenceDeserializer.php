<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use TypeError;

/**
 * MediaWiki revision SPECIFIC deserializer. Not for general use such as in the presentation layer.
 *
 * Related @see SchemaPresentationSerializer
 */
class SchemaPersistenceDeserializer {

	public function __construct(
		private readonly PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function deserialize( SchemaName $schemaName, string $json ): Schema {
		$json = json_decode( $json, true );

		if ( !is_array( $json ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		// A value of the wrong type, which a save refuses but an import can store, arrives as a TypeError.
		try {
			return new Schema(
				name: $schemaName,
				description: $json['description'] ?? '',
				properties: $this->propertiesFromJson( $json ),
			);
		}
		catch ( TypeError $error ) {
			throw new InvalidArgumentException( 'Invalid Schema JSON', 0, $error );
		}
	}

	/**
	 * Properties of an unregistered type are not dropped here: PropertyDefinition::fromJson
	 * preserves them. Only structurally invalid definitions are skipped, such as one with a field
	 * of the wrong type.
	 */
	private function propertiesFromJson( array $json ): PropertyDefinitions {
		$properties = [];

		foreach ( $json['propertyDefinitions'] ?? [] as $propertyName => $property ) {
			if ( is_string( $propertyName ) ) {
				try {
					$properties[$propertyName] = PropertyDefinition::fromJson( $property, $this->propertyTypeLookup );
				}
				catch ( InvalidArgumentException | TypeError ) {
					// TODO: log error
				}
			}
			else {
				// TODO: log error
			}
		}

		return new PropertyDefinitions( $properties );
	}

}
