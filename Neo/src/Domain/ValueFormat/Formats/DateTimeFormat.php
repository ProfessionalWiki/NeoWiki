<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class DateTimeFormat implements ValueFormat {

	public const NAME = 'dateTime';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): DateTimeProperty {
		return DateTimeProperty::fromPartialJson( $core, $property );
	}

}
