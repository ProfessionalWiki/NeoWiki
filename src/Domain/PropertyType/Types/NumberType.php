<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;

class NumberType implements PropertyType {

	public const NAME = 'number';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Number;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): NumberProperty {
		return NumberProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		return $value->toScalars();
	}

}
