<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class DateFormat implements ValueFormat {

	public const NAME = 'date';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): DateProperty {
		return DateProperty::fromPartialJson( $core, $property );
	}

}
