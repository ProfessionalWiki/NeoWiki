<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

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

	public function getProperty( string $name ): PropertyDefinition {
		if ( array_key_exists( $name, $this->properties ) ) {
			return $this->properties[$name];
		}

		throw new InvalidArgumentException( "Property $name does not exist" );
	}

}
