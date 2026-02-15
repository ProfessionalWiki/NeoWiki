<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

interface PropertyType {

	public function getTypeName(): string;

	public function getValueType(): ValueType;

	/**
	 * @throws InvalidArgumentException
	 */
	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): PropertyDefinition;

}
