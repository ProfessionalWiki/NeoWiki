<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DatePrecision;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\PartialDate;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class DateType implements PropertyType {

	public const NAME = 'date';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): DateProperty {
		return DateProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof DateProperty ) {
			return [];
		}

		$rawValue = $this->extractFirstString( $value );

		if ( $rawValue === null ) {
			return $definition->isRequired()
				? [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ]
				: [];
		}

		$date = PartialDate::tryParse( $rawValue );

		if ( $date === null ) {
			return [ new Violation( propertyName: null, code: 'invalid-date', severity: Severity::Error ) ];
		}

		return array_values( array_filter( [
			$this->checkMinPrecision( $date, $definition->getMinPrecision(), $definition->severityOf( 'minPrecision' ) ),
			$this->checkMinimum( $date, $definition->getMinimum(), $definition->severityOf( 'minimum' ) ),
			$this->checkMaximum( $date, $definition->getMaximum(), $definition->severityOf( 'maximum' ) ),
		] ) );
	}

	private function checkMinPrecision( PartialDate $date, ?DatePrecision $minPrecision, Severity $severity ): ?Violation {
		if ( $minPrecision === null || $date->precision->isAtLeast( $minPrecision ) ) {
			return null;
		}
		return new Violation( propertyName: null, code: 'min-precision-' . $minPrecision->value, severity: $severity );
	}

	/**
	 * A date of year or month precision stands for a range of days, and so can a bound. Only a
	 * date whose every day falls before the minimum violates it.
	 */
	private function checkMinimum( PartialDate $date, ?string $minString, Severity $severity ): ?Violation {
		if ( $minString === null ) {
			return null;
		}
		$min = PartialDate::tryParse( $minString );
		if ( $min === null || $date->latest >= $min->earliest ) {
			return null;
		}
		return new Violation( propertyName: null, code: 'min-value', args: [ $minString ], severity: $severity );
	}

	private function checkMaximum( PartialDate $date, ?string $maxString, Severity $severity ): ?Violation {
		if ( $maxString === null ) {
			return null;
		}
		$max = PartialDate::tryParse( $maxString );
		if ( $max === null || $date->earliest <= $max->latest ) {
			return null;
		}
		return new Violation( propertyName: null, code: 'max-value', args: [ $maxString ], severity: $severity );
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

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof StringValue ? $value->strings : [];
	}

}
