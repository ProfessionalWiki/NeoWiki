<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class RelationType implements PropertyType {

	public const NAME = 'relation';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Relation;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): RelationProperty {
		return RelationProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof RelationProperty ) {
			return [];
		}

		$relations = $value instanceof RelationValue ? $value->relations : [];

		if ( $definition->isRequired() && $relations === [] ) {
			return [ new Violation( propertyName: null, code: 'required' ) ];
		}

		$violations = [];

		if ( !$definition->allowsMultipleValues() && count( $relations ) > 1 ) {
			$violations[] = new Violation( propertyName: null, code: 'single-value-only' );
		}

		return $violations;
	}

}
