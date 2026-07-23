<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class SelectType implements PropertyType {

	public const NAME = 'select';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): SelectProperty {
		return SelectProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof SelectProperty ) {
			return [];
		}

		$parts = $value instanceof StringValue ? $value->strings : [];

		if ( $definition->isRequired() && $parts === [] ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$validIds = [];
		foreach ( $definition->getOptions() as $option ) {
			$validIds[ $option->getId() ] = true;
		}

		$violations = [];

		foreach ( $parts as $index => $part ) {
			if ( !isset( $validIds[ $part ] ) ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-option',
					args: [ $part ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'options' ),
				);
			}
		}

		if ( !$definition->allowsMultipleValues() && count( $parts ) > 1 ) {
			$violations[] = new Violation( propertyName: null, code: 'single-value-only', severity: Severity::Error );
		}

		return $violations;
	}

}
