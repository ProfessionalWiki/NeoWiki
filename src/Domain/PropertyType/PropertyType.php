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
	 * @return string[] Names of Attributes that are Display Attributes (overridable in Views).
	 */
	public function getDisplayAttributeNames(): array;

	/**
	 * @throws InvalidArgumentException
	 */
	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): PropertyDefinition;

}
