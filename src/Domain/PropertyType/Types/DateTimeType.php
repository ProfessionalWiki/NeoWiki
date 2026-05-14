<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class DateTimeType implements PropertyType {

	public const NAME = 'dateTime';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): DateTimeProperty {
		return DateTimeProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof DateTimeProperty ) {
			return [];
		}

		$rawValue = $this->extractFirstString( $value );

		if ( $rawValue === null ) {
			if ( $definition->isRequired() ) {
				return [ new Violation( propertyName: null, code: 'required' ) ];
			}
			return [];
		}

		$parsed = DateTimeProperty::parseStrictDateTime( $rawValue );

		if ( $parsed === null ) {
			return [ new Violation( propertyName: null, code: 'invalid-datetime' ) ];
		}

		$violations = [];

		$minimumString = $definition->getMinimum();
		if ( $minimumString !== null ) {
			$min = DateTimeProperty::parseStrictDateTime( $minimumString );
			if ( $min !== null && $parsed < $min ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'min-value',
					args: [ $minimumString ],
				);
			}
		}

		$maximumString = $definition->getMaximum();
		if ( $maximumString !== null ) {
			$max = DateTimeProperty::parseStrictDateTime( $maximumString );
			if ( $max !== null && $parsed > $max ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'max-value',
					args: [ $maximumString ],
				);
			}
		}

		return $violations;
	}

	private function extractFirstString( NeoValue $value ): ?string {
		if ( !$value instanceof StringValue ) {
			return null;
		}
		if ( $value->strings === [] ) {
			return null;
		}
		$first = trim( $value->strings[0] );
		return $first === '' ? null : $first;
	}

}
