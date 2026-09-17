<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use LogicException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

/**
 * Indexes its values as the Property Definition it was handed, so a test can tell which definition
 * reached the Property Type.
 */
class DefinitionReportingPropertyType implements PropertyType {

	public function __construct(
		private readonly string $typeName
	) {
	}

	public function getTypeName(): string {
		return $this->typeName;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): PropertyDefinition {
		throw new LogicException( 'Definitions of this type are built by the tests, not deserialized' );
	}

	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		return [];
	}

	/**
	 * @return string[]
	 */
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return [
			$definition === null ? 'no definition' : 'definition of type ' . $definition->getPropertyType()
		];
	}

}
