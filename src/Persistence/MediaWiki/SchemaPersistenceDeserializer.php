<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Labels;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use Psr\Log\LoggerInterface;

/**
 * MediaWiki revision SPECIFIC deserializer. Not for general use such as in the presentation layer.
 *
 * Related @see SchemaPresentationSerializer
 */
class SchemaPersistenceDeserializer {

	public function __construct(
		private readonly PropertyTypeLookup $propertyTypeLookup,
		private readonly LoggerInterface $logger,
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

		return new Schema(
			name: $schemaName,
			description: $json['description'] ?? '',
			properties: $this->propertiesFromJson( $schemaName, $json ),
			subjectsAreDependent: ( $json['subjectsAreDependent'] ?? false ) === true,
			labels: Labels::fromJson( $json['labels'] ?? [] ),
		);
	}

	/**
	 * Properties of an unregistered type are not dropped here: PropertyDefinition::fromJson
	 * preserves them. Only structurally invalid definitions are skipped.
	 */
	private function propertiesFromJson( SchemaName $schemaName, array $json ): PropertyDefinitions {
		$properties = [];

		foreach ( $json['propertyDefinitions'] ?? [] as $propertyName => $property ) {
			if ( !is_string( $propertyName ) ) {
				$this->logger->warning(
					'NeoWiki left out property {property} of Schema {schema}: {message}',
					[
						'property' => (string)$propertyName,
						'schema' => $schemaName->getText(),
						'message' => 'its name is an integer',
					]
				);
				continue;
			}

			try {
				$properties[$propertyName] = PropertyDefinition::fromJson( $property, $this->propertyTypeLookup );
			}
			catch ( InvalidArgumentException $exception ) {
				$this->logger->warning(
					'NeoWiki left out property {property} of Schema {schema}: {message}',
					[
						'property' => $propertyName,
						'schema' => $schemaName->getText(),
						'message' => $exception->getMessage(),
						'exception' => $exception,
					]
				);
			}
		}

		return new PropertyDefinitions( $properties );
	}

}
