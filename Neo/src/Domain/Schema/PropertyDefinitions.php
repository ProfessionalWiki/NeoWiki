<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use OutOfBoundsException;

class PropertyDefinitions {

	/**
	 * @var array<string, PropertyDefinition>
	 */
	private array $properties = [];

	/**
	 * @param array<string, PropertyDefinition> $properties Property definitions indexed by name
	 */
	public function __construct( array $properties ) {
		$this->properties = $properties;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getProperty( string $name ): PropertyDefinition {
		if ( array_key_exists( $name, $this->properties ) ) {
			return $this->properties[$name];
		}

		throw new OutOfBoundsException( "Property '$name' does not exist" );
	}

	public function hasProperty( string $name ): bool {
		return array_key_exists( $name, $this->properties );
	}

	/**
	 * @param callable(PropertyDefinition):bool $filter
	 */
	public function filter( callable $filter ): self {
		return new self( array_filter( $this->properties, $filter ) );
	}

	public function getRelations(): self {
		return $this->filter(
			fn( PropertyDefinition $property ) => $property->getFormat() === ValueFormat::Relation
		);
	}

	/**
	 * @return array<string, PropertyDefinition>
	 */
	public function asMap(): array {
		return $this->properties;
	}

}
