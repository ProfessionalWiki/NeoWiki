<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class ColorType implements PropertyType {

	public const NAME = 'color';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): ColorProperty {
		return ColorProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue ) {
			return [];
		}

		if ( !$definition instanceof ColorProperty ) {
			return [];
		}

		if ( $definition->isRequired() && $value->strings === [] ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$allowed = $definition->getAllowedColors();
		$allowedSet = $allowed === [] ? null : array_flip( $allowed );

		$violations = [];

		foreach ( $value->strings as $index => $part ) {
			if ( preg_match( ColorProperty::HEX_COLOR_REGEX, $part ) !== 1 ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-color',
					args: [ $part ],
					valuePartIndex: $index,
					severity: Severity::Error,
				);
				continue;
			}

			if ( $allowedSet !== null && !isset( $allowedSet[$part] ) ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-option',
					args: [ $part ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'allowedColors' ),
				);
			}
		}

		return $violations;
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof StringValue ? $value->strings : [];
	}

}
