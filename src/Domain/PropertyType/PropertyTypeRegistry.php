<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;

class PropertyTypeRegistry implements PropertyTypeLookup {

	/**
	 * @var array<string, PropertyType> Keys are type names
	 */
	private array $types = [];

	public static function withCoreTypes(): self {
		$registry = new self();
		$registry->registerType( new TextType() );
		$registry->registerType( new UrlType() );
		$registry->registerType( new NumberType() );
		$registry->registerType( new RelationType() );
		return $registry;
	}

	public function registerType( PropertyType $type ): void {
		$this->types[$type->getTypeName()] = $type;
	}

	public function getType( string $typeName ): ?PropertyType {
		return $this->types[$typeName] ?? null;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getTypeOrThrow( string $typeName ): PropertyType {
		$type = $this->getType( $typeName );

		if ( $type === null ) {
			throw new OutOfBoundsException( "Unknown property type: $typeName" );
		}

		return $type;
	}

}
