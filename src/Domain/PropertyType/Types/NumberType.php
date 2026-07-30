<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class NumberType implements PropertyType {

	public const NAME = 'number';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Number;
	}

	public function getDisplayAttributeNames(): array {
		return [ 'precision' ];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): NumberProperty {
		return NumberProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof NumberProperty ) {
			return [];
		}

		if ( !$value instanceof NumberValue ) {
			if ( $definition->isRequired() ) {
				return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
			}
			return [];
		}

		$violations = [];

		if ( $definition->hasMinimum() && $value->number < $definition->getMinimum() ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'min-value',
				args: [ $definition->getMinimum() ],
				severity: $definition->severityOf( 'minimum' ),
			);
		}

		if ( $definition->hasMaximum() && $value->number > $definition->getMaximum() ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'max-value',
				args: [ $definition->getMaximum() ],
				severity: $definition->severityOf( 'maximum' ),
			);
		}

		return $violations;
	}

}
