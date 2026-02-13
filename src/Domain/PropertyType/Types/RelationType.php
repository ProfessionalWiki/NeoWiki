<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;

class RelationType implements PropertyType {

	public const NAME = 'relation';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Relation;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): RelationProperty {
		return RelationProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		return PropertyType::NO_NEO4J_VALUE;
	}

}
