<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;

/**
 * Not for general use such as in the persistence layer.
 *
 * Related @see SchemaPersistenceDeserializer
 */
class SchemaPresentationSerializer {

	public function serialize( Schema $schema ): string {
		$json = json_encode( $this->toArray( $schema ) );

		if ( $json === false ) {
			throw new \RuntimeException( 'Failed to JSON encode schema data' );
		}

		return $json;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray( Schema $schema ): array {
		return [
			'description' => $schema->getDescription(),
			'propertyDefinitions' => $this->propertiesToJson( $schema->getAllProperties() ),
		];
	}

	private function propertiesToJson( PropertyDefinitions $properties ): array {
		$json = [];

		foreach ( $properties->asMap() as $propertyName => $property ) {
			$json[$propertyName] = $property->toJson();
		}

		return $json;
	}

}
