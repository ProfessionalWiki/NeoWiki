<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class NumberFormat implements ValueFormat {

	public const NAME = 'number';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Number;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): NumberProperty {
		return NumberProperty::fromPartialJson( $core, $property );
	}

}
