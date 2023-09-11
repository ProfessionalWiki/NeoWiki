<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

interface ValueFormat {

	public function getFormatName(): string;

	public function getValueType(): ValueType;

	/**
	 * @throws InvalidArgumentException
	 */
	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): PropertyDefinition;

}
