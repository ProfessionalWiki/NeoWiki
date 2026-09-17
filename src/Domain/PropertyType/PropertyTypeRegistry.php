<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;

class PropertyTypeRegistry implements PropertyTypeLookup {

	/**
	 * @var array<string, PropertyType> Keys are type names
	 */
	private array $types = [];

	/**
	 * @param SchemaReferenceParser $schemaReferenceParser Reads the `targetSchema` of a relation
	 *   property, which is a Schema reference like any other and names one Schema however written.
	 */
	public static function withCoreTypes( SchemaReferenceParser $schemaReferenceParser ): self {
		$registry = new self();
		$registry->registerType( new TextType() );
		$registry->registerType( new UrlType() );
		$registry->registerType( new NumberType() );
		$registry->registerType( new SelectType() );
		$registry->registerType( new BooleanType() );
		$registry->registerType( new RelationType( $schemaReferenceParser ) );
		$registry->registerType( new DateTimeType() );
		$registry->registerType( new DateType() );
		return $registry;
	}

	public function registerType( PropertyType $type ): void {
		$this->types[$type->getTypeName()] = $type;
	}

	public function getType( string $typeName ): ?PropertyType {
		return $this->types[$typeName] ?? null;
	}

}
