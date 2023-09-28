<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\CurrencyProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class CurrencyFormat implements ValueFormat {

	public const NAME = 'currency';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Number;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): CurrencyProperty {
		return CurrencyProperty::fromPartialJson( $core, $property );
	}

	public function buildNeo4jValue( NeoValue $value ): mixed {
		return $value->toScalars();
	}

}
