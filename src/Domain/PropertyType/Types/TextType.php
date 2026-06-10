<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class TextType implements PropertyType {

	public const NAME = 'text';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): TextProperty {
		return TextProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue ) {
			return [];
		}

		if ( !$definition instanceof TextProperty ) {
			return [];
		}

		$violations = [];

		$hasContent = false;
		foreach ( $value->strings as $part ) {
			if ( trim( $part ) !== '' ) {
				$hasContent = true;
				break;
			}
		}

		if ( $definition->isRequired() && !$hasContent ) {
			$violations[] = new Violation( propertyName: null, code: 'required' );
		}

		$violations = array_merge( $violations, $this->validateLengths( $value, $definition ) );

		if ( $definition->enforcesUniqueValues()
			&& count( array_unique( $value->strings ) ) !== count( $value->strings )
		) {
			$violations[] = new Violation( propertyName: null, code: 'unique' );
		}

		return $violations;
	}

	/**
	 * @return Violation[]
	 */
	private function validateLengths( StringValue $value, TextProperty $definition ): array {
		if ( !$definition->hasMinLength() && !$definition->hasMaxLength() ) {
			return [];
		}

		$violations = [];

		foreach ( $value->strings as $index => $part ) {
			$length = mb_strlen( trim( $part ) );

			if ( $length === 0 ) {
				continue;
			}

			if ( $definition->hasMinLength() && $length < $definition->getMinLength() ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'min-length',
					args: [ $definition->getMinLength() ],
					valuePartIndex: $index,
				);
			}

			if ( $definition->hasMaxLength() && $length > $definition->getMaxLength() ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'max-length',
					args: [ $definition->getMaxLength() ],
					valuePartIndex: $index,
				);
			}
		}

		return $violations;
	}

}
