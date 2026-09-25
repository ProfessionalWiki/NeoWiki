<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;

readonly class Schema {

	public function __construct(
		private SchemaName $name,
		private string $description,
		private PropertyDefinitions $properties,
		private ?LabelTemplate $labelTemplate = null,
	) {
	}

	public function getName(): SchemaName {
		return $this->name;
	}

	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getProperty( string|PropertyName $name ): PropertyDefinition {
		return $this->properties->getProperty( $name );
	}

	public function hasProperty( string|PropertyName $name ): bool {
		return $this->properties->hasProperty( $name );
	}

	public function isRelationProperty( string $name ): bool {
		return $this->hasProperty( $name )
			&& $this->properties->getProperty( $name )->getPropertyType() === RelationType::NAME;
	}

	public function getRelationProperties(): PropertyDefinitions {
		return $this->properties->getRelations();
	}

	public function getAllProperties(): PropertyDefinitions {
		return $this->properties;
	}

	/**
	 * The definition the Statement's value was written under; null when the Schema lacks the property or
	 * gives it another type.
	 */
	public function definitionOf( Statement $statement ): ?PropertyDefinition {
		if ( !$this->hasProperty( $statement->getPropertyName() ) ) {
			return null;
		}

		$definition = $this->getProperty( $statement->getPropertyName() );

		return $definition->getPropertyType() === $statement->getPropertyType() ? $definition : null;
	}

	public function getLabelTemplate(): ?LabelTemplate {
		return $this->labelTemplate;
	}

}
