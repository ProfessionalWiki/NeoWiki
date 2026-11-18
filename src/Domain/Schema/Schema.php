<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use OutOfBoundsException;

readonly class Schema {

	/**
	 * @param array<string, string> $labels Keyed by lowercase language tag
	 */
	public function __construct(
		private SchemaName $name,
		private string $description,
		private PropertyDefinitions $properties,
		private bool $subjectsAreDependent,
		private array $labels = [],
	) {
	}

	public function getName(): SchemaName {
		return $this->name;
	}

	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @return array<string, string> Keyed by lowercase language tag
	 */
	public function getLabels(): array {
		return $this->labels;
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

	public function getRelationProperties(): PropertyDefinitions {
		return $this->properties->getRelations();
	}

	public function getAllProperties(): PropertyDefinitions {
		return $this->properties;
	}

	public function subjectsAreDependent(): bool {
		return $this->subjectsAreDependent;
	}

}
