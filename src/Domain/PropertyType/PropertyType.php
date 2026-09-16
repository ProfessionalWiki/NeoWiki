<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
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

	/**
	 * Validate a Value against its PropertyDefinition.
	 *
	 * Returned Violations carry no propertyName — SubjectValidator attaches it via
	 * Violation::withPropertyName() since PropertyType plugins don't know their own
	 * property name.
	 *
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array;

	/**
	 * The strings someone would search for to find the Value; an empty array keeps the type's values
	 * out of the search index. The Property Definition is the one the Value is stored under, or null
	 * when the Schema does not declare the property with this type.
	 *
	 * @return string[]
	 */
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array;

}
