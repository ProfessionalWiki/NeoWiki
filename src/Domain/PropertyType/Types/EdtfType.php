<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use EDTF\EdtfFactory;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\EdtfProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class EdtfType implements PropertyType {

	public const NAME = 'edtf';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): EdtfProperty {
		return EdtfProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof EdtfProperty ) {
			return [];
		}

		$rawValue = $this->extractFirstString( $value );

		if ( $rawValue === null ) {
			return $definition->isRequired()
				? [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ]
				: [];
		}

		if ( EdtfFactory::newValidator()->isValidEdtf( $rawValue ) ) {
			return [];
		}

		return [ new Violation( propertyName: null, code: 'invalid-edtf', severity: Severity::Error ) ];
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
