<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Scribunto;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;

class SchemaLuaSerializer {

	public function toLuaTable( Schema $schema ): array {
		$result = [ 'name' => $schema->getName()->getText() ];

		$description = $schema->getDescription();
		if ( $description !== '' ) {
			$result['description'] = $description;
		}

		$labelTemplate = $schema->getLabelTemplate();
		if ( $labelTemplate !== null ) {
			$result['labelTemplate'] = $labelTemplate->text;
		}

		$result['properties'] = $this->propertiesToLuaList( $schema );
		return $result;
	}

	private function propertiesToLuaList( Schema $schema ): array {
		$properties = [];
		$index = 1;
		foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
			$properties[$index] = $this->propertyToLuaTable( $name, $property );
			$index++;
		}
		return $properties;
	}

	private function propertyToLuaTable( string $name, PropertyDefinition $property ): array {
		$entry = [
			'name' => $name,
			'type' => $property->getPropertyType(),
			'required' => $property->isRequired(),
		];

		$description = $property->getDescription();
		if ( $description !== '' ) {
			$entry['description'] = $description;
		}

		if ( $property->hasDefault() ) {
			$entry['default'] = $property->getDefault();
		}

		return $entry + $this->normalise( $property->nonCoreToJson() );
	}

	private function normalise( array $values ): array {
		// If the input is a 0-indexed PHP list, filtering may leave holes; compact
		// and re-index to 1-based at the end so Lua's ipairs() works.
		$inputWasList = $values !== [] && array_is_list( $values );

		$result = [];
		foreach ( $values as $key => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = $this->normalise( $value );
			}
			$result[$key] = $value;
		}

		if ( $inputWasList && $result !== [] ) {
			$compact = array_values( $result );
			return array_combine( range( 1, count( $compact ) ), $compact );
		}

		return $result;
	}

}
