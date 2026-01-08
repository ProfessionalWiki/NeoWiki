<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\RelationFormat;

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
	public function getProperty( string|PropertyName $name ): PropertyDefinition {
		if ( array_key_exists( (string)$name, $this->properties ) ) {
			return $this->properties[(string)$name];
		}

		throw new OutOfBoundsException( "Property '$name' does not exist" );
	}

	public function hasProperty( string|PropertyName $name ): bool {
		return array_key_exists( (string)$name, $this->properties );
	}

	/**
	 * @param callable(PropertyDefinition):bool $filter
	 */
	public function filter( callable $filter ): self {
		return new self( array_filter( $this->properties, $filter ) );
	}

	public function getRelations(): self {
		return $this->filter(
			fn( PropertyDefinition $property ) => $property->getFormat() === RelationFormat::NAME
		);
	}

	/**
	 * @return array<string, PropertyDefinition>
	 */
	public function asMap(): array {
		return $this->properties;
	}

}
