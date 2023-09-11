<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\ProgressProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\ValueFormat;

class ProgressFormat implements ValueFormat {

	public const NAME = 'progress';

	public function getFormatName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Number;
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): ProgressProperty {
		return ProgressProperty::fromPartialJson( $core, $property );
	}

}
