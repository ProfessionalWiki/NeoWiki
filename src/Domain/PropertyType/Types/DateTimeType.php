<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use DateTimeImmutable;
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
			return $definition->isRequired()
				? [ new Violation( propertyName: null, code: 'required' ) ]
				: [];
		}

		$parsed = DateTimeProperty::parseStrictDateTime( $rawValue );

		if ( $parsed === null ) {
			return [ new Violation( propertyName: null, code: 'invalid-datetime' ) ];
		}

		return array_values( array_filter( [
			$this->checkMinimum( $parsed, $definition->getMinimum() ),
			$this->checkMaximum( $parsed, $definition->getMaximum() ),
		] ) );
	}

	private function checkMinimum( DateTimeImmutable $parsed, ?string $minString ): ?Violation {
		if ( $minString === null ) {
			return null;
		}
		$min = DateTimeProperty::parseStrictDateTime( $minString );
		if ( $min === null || $parsed >= $min ) {
			return null;
		}
		return new Violation( propertyName: null, code: 'min-value', args: [ $minString ] );
	}

	private function checkMaximum( DateTimeImmutable $parsed, ?string $maxString ): ?Violation {
		if ( $maxString === null ) {
			return null;
		}
		$max = DateTimeProperty::parseStrictDateTime( $maxString );
		if ( $max === null || $parsed <= $max ) {
			return null;
		}
		return new Violation( propertyName: null, code: 'max-value', args: [ $maxString ] );
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
