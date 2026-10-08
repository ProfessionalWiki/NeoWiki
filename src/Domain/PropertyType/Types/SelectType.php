<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizesRawValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Domain\Value\NeoValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class SelectType implements PropertyType, NormalizesRawValue {

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

		$validIds = array_flip( $definition->getOptionIds() );

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
			$violations[] = new Violation(
				propertyName: null,
				code: 'single-value-only',
				severity: $definition->severityOf( 'multiple' ),
			);
		}

		return $violations;
	}

	/**
	 * The stored option ids are read as their labels from the definition.
	 */
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue || !$definition instanceof SelectProperty ) {
			return [];
		}

		$labelsById = [];
		foreach ( $definition->getOptions() as $option ) {
			$labelsById[ $option->getId() ] = $option->getLabel();
		}

		$labels = [];

		foreach ( $value->strings as $id ) {
			if ( isset( $labelsById[ $id ] ) ) {
				$labels[] = $labelsById[ $id ];
			}
		}

		return $labels;
	}

	/**
	 * A Statement stores an option's id. Callers may send the id, the option's label, or an
	 * `{id, label}` object naming one or both, so that a caller without the Schema in hand - a CSV import,
	 * a script, an agent - can write the label a human knows instead of an opaque id.
	 */
	public function normalizeRawValue( mixed $raw, PropertyDefinition $definition ): mixed {
		if ( !$definition instanceof SelectProperty ) {
			return $raw;
		}

		if ( is_array( $raw ) && array_is_list( $raw ) ) {
			return $this->normalizeList( $raw, $definition );
		}

		return $this->normalizePart( $raw, $definition );
	}

	/**
	 * @param list<mixed> $raw
	 * @return list<string>
	 */
	private function normalizeList( array $raw, SelectProperty $property ): array {
		return array_values( array_unique(
			array_map( fn( mixed $part ): string => $this->normalizePart( $part, $property ), $raw )
		) );
	}

	private function normalizePart( mixed $raw, SelectProperty $property ): string {
		if ( is_string( $raw ) ) {
			return $this->normalizeString( $raw, $property );
		}

		if ( is_array( $raw ) ) {
			return $this->normalizeObject( $raw, $property );
		}

		throw new InvalidArgumentException( 'a part must be a string or an object' );
	}

	/**
	 * A string naming no option is left as sent, for {@see self::validate()} to report at the
	 * severity the Schema sets.
	 */
	private function normalizeString( string $raw, SelectProperty $property ): string {
		$matched = $this->findById( $property, $raw ) ?? $this->findByLabel( $property, $raw );

		return $matched?->getId() ?? $raw;
	}

	/**
	 * An object naming only a label matches labels alone, and one naming an id must match that id:
	 * neither falls back to the other, so a stale id cannot silently become some other option. An
	 * object naming no option is refused: unlike a string, it cannot be stored as sent.
	 *
	 * @param array<mixed> $raw
	 */
	private function normalizeObject( array $raw, SelectProperty $property ): string {
		$id = $this->stringMember( $raw, 'id' );
		$label = $this->stringMember( $raw, 'label' );

		if ( $id === null && $label === null ) {
			throw new InvalidArgumentException( 'an object needs an id or a label' );
		}

		if ( $id === null ) {
			/** @var string $label */
			return $this->findByLabel( $property, $label )?->getId()
				?? throw new InvalidArgumentException( "no option is labelled \"$label\"" );
		}

		$matched = $this->findById( $property, $id )
			?? throw new InvalidArgumentException( "no option has the id \"$id\"" );

		if ( $label !== null && $matched->foldedLabel() !== SelectOption::foldLabel( $label ) ) {
			throw new InvalidArgumentException( "option \"$id\" is not labelled \"$label\"" );
		}

		return $matched->getId();
	}

	/**
	 * A member that is present but not a string is a malformed value, not an absent member:
	 * ignoring it would let `{ "id": 42, "label": "Draft" }` resolve by label and hide the
	 * caller's bug. Null still counts as absent.
	 *
	 * @param array<mixed> $raw
	 */
	private function stringMember( array $raw, string $member ): ?string {
		$value = $raw[$member] ?? null;

		if ( $value !== null && !is_string( $value ) ) {
			throw new InvalidArgumentException( "an object's $member must be a string" );
		}

		return $value;
	}

	private function findById( SelectProperty $property, string $id ): ?SelectOption {
		foreach ( $property->getOptions() as $option ) {
			if ( $option->getId() === $id ) {
				return $option;
			}
		}

		return null;
	}

	private function findByLabel( SelectProperty $property, string $label ): ?SelectOption {
		$foldedLabel = SelectOption::foldLabel( $label );

		foreach ( $property->getOptions() as $option ) {
			if ( $option->foldedLabel() === $foldedLabel ) {
				return $option;
			}
		}

		return null;
	}

}
