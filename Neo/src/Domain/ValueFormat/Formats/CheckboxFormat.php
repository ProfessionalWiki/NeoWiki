<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\CheckboxProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class CheckboxFormat implements ValueFormat {

	public const NAME = 'checkbox';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Boolean;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): CheckboxProperty {
		return CheckboxProperty::fromPartialJson( $core, $property );
	}

}
