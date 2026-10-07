<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\MonolingualTextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class MonolingualTextType implements PropertyType {

	public const NAME = 'monolingualText';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::MonolingualText;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): MonolingualTextProperty {
		return MonolingualTextProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof MonolingualTextValue ) {
			return [];
		}

		if ( !$definition instanceof MonolingualTextProperty ) {
			return [];
		}

		$violations = [];

		if ( $definition->isRequired() && $value->isEmpty() ) {
			$violations[] = new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) );
		}

		$violations = array_merge( $violations, $this->validateLengths( $value, $definition ) );

		if ( $definition->enforcesUniqueValues() && $this->hasDuplicateParts( $value ) ) {
			$violations[] = new Violation( propertyName: null, code: 'unique', severity: $definition->severityOf( 'uniqueItems' ) );
		}

		if ( !$definition->allowsMultipleValues() && count( $value->parts ) > 1 ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'single-value-only',
				severity: $definition->severityOf( 'multiple' ),
			);
		}

		return $violations;
	}

	/**
	 * @return Violation[]
	 */
	private function validateLengths( MonolingualTextValue $value, MonolingualTextProperty $definition ): array {
		if ( !$definition->hasMinLength() && !$definition->hasMaxLength() ) {
			return [];
		}

		$violations = [];

		foreach ( $value->parts as $index => $part ) {
			$length = mb_strlen( $part->text );

			if ( $definition->hasMinLength() && $length < $definition->getMinLength() ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'min-length',
					args: [ $definition->getMinLength() ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'minLength' ),
				);
			}

			if ( $definition->hasMaxLength() && $length > $definition->getMaxLength() ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'max-length',
					args: [ $definition->getMaxLength() ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'maxLength' ),
				);
			}
		}

		return $violations;
	}

	/**
	 * The same text in two languages is not a duplicate, so uniqueness compares the pair.
	 */
	private function hasDuplicateParts( MonolingualTextValue $value ): bool {
		$pairs = array_map(
			static fn ( MonolingualText $part ): string => $part->language . "\n" . $part->text,
			$value->parts
		);

		return count( array_unique( $pairs ) ) !== count( $pairs );
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		if ( !$value instanceof MonolingualTextValue ) {
			return [];
		}

		return $value->getTexts();
	}

}
