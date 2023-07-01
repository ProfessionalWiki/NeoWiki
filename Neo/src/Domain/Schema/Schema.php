<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use OutOfBoundsException;

class Schema {

	public function __construct(
		private readonly SchemaId $id,
		private readonly string $description,
		private readonly PropertyDefinitions $properties,
	) {
	}

	public function getId(): SchemaId {
		return $this->id;
	}

	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getProperty( string $name ): PropertyDefinition {
		return $this->properties->getProperty( $name );
	}

	public function hasProperty( string $name ): bool {
		return $this->properties->hasProperty( $name );
	}

	public function isRelationProperty( string $name ): bool {
		return $this->hasProperty( $name )
			&& $this->properties->getProperty( $name )->getFormat() === ValueFormat::Relation;
	}

	public function getRelationProperties(): PropertyDefinitions {
		return $this->properties->getRelations();
	}

	public function getAllProperties(): PropertyDefinitions {
		return $this->properties;
	}

}
