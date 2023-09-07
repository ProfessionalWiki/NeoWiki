<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class SchemaSerializer {

	private array $typeMapping = [
		BooleanProperty::class => ValueType::Boolean,
		NumberProperty::class => ValueType::Number,
		RelationProperty::class => ValueType::Relation,
		StringProperty::class => ValueType::String,
	];

	public function serialize( Schema $schema ): string {
		$data = [
			'description' => $schema->getDescription(),
			'propertyDefinitions' => $this->propertiesToJson( $schema->getAllProperties() ),
		];

		$json = json_encode( $data );

		if ( $json === false ) {
			throw new \RuntimeException( 'Failed to JSON encode schema data' );
		}

		return $json;
	}

	private function propertiesToJson( PropertyDefinitions $properties ): array {
		$json = [];

		foreach ( $properties->asMap() as $propertyName => $property ) {
			$json[$propertyName] = $this->propertyDefinitionToJson( $property );
		}

		return $json;
	}

	private function propertyDefinitionToJson( PropertyDefinition $property ): array {
		$data = [
			'type' => $this->typeMapping[get_class( $property )],
			'description' => $property->getDescription(),
			'required' => $property->isRequired(),
			'default' => $property->getDefault(),
			'multiple' => $property->isMultiple(),
			'format' => $property->getFormat(),
		];

		if ( $property instanceof RelationProperty ) {
			$data['relation'] = $property->getRelationType()->getText();
			$data['targetSchema'] = $property->getTargetSchema()->getText();
		}

		if ( $property instanceof NumberProperty ) {
			if ( $property->getMinimum() !== null ) {
				$data['minimum'] = $property->getMinimum();
			}
			if ( $property->getMaximum() !== null ) {
				$data['maximum'] = $property->getMaximum();
			}
		}

		return $data;
	}

}
