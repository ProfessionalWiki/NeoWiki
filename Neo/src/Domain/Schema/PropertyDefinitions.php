<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

class PropertyDefinitions {

	/**
	 * @var array<string, PropertyDefinition>
	 */
	private array $properties = [];

	public function __construct( PropertyDefinition ...$properties ) {
		foreach ( $properties as $property ) {
			$this->properties[$property->getName()] = $property;
		}
	}

	public function getProperty( string $name ): PropertyDefinition {
		if ( array_key_exists( $name, $this->properties ) ) {
			return $this->properties[$name];
		}

		throw new InvalidArgumentException( "Property $name does not exist" );
	}

}
