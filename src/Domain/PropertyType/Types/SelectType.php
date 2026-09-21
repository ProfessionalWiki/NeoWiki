<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizationResult;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizesRawValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
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
	public function normalizeRawValue( mixed $raw, PropertyDefinition $definition ): NormalizationResult {
		if ( !$definition instanceof SelectProperty ) {
			return NormalizationResult::normalized( $raw );
		}

		if ( is_array( $raw ) && array_is_list( $raw ) ) {
			return $this->normalizeList( $raw, $definition );
		}

		$result = $this->normalizePart( $raw, $definition );

		return $result->violation === null
			? $result
			: NormalizationResult::unresolvable( $result->value, $result->violation->withValuePartIndex( 0 ) );
	}

	/**
	 * @param list<mixed> $raw
	 */
	private function normalizeList( array $raw, SelectProperty $property ): NormalizationResult {
		$parts = [];
		$violation = null;

		foreach ( $raw as $index => $part ) {
			$result = $this->normalizePart( $part, $property );

			$parts[] = $result->value;
			$violation ??= $result->violation?->withValuePartIndex( $index );
		}

		return $violation === null
			? NormalizationResult::normalized( $parts )
			: NormalizationResult::unresolvable( $parts, $violation );
	}

	private function normalizePart( mixed $raw, SelectProperty $property ): NormalizationResult {
		// An empty value is a cleared field, not a reference to an option. Left for
		// StatementListBuilder to drop, rather than reported as naming no option.
		if ( is_string( $raw ) && trim( $raw ) === '' ) {
			return NormalizationResult::normalized( $raw );
		}

		if ( is_string( $raw ) ) {
			return $this->normalizeScalar( $raw, $property );
		}

		if ( is_array( $raw ) ) {
			return $this->normalizeObject( $raw, $property );
		}

		return NormalizationResult::unresolvable(
			$raw,
			$this->violation( 'select-value-not-string-or-object' )
		);
	}

	private function normalizeScalar( string $raw, SelectProperty $property ): NormalizationResult {
		$matched = $this->findById( $property, $raw ) ?? $this->findByLabel( $property, $raw );

		return $this->resultFor( $matched, $raw, $raw );
	}

	/**
	 * @param array<mixed> $raw
	 */
	private function normalizeObject( array $raw, SelectProperty $property ): NormalizationResult {
		$memberViolation = $this->nonStringMemberViolation( $raw );

		if ( $memberViolation !== null ) {
			return NormalizationResult::unresolvable( $raw, $memberViolation );
		}

		/** @var array{id?: ?string, label?: ?string} $raw nonStringMemberViolation() settled this */
		$id = $raw['id'] ?? null;
		$label = $raw['label'] ?? null;

		if ( $id === null && $label === null ) {
			return NormalizationResult::unresolvable(
				$raw,
				$this->violation( 'select-object-without-id-or-label' )
			);
		}

		// An object naming only a label matches labels alone: unlike a bare string it never falls
		// back to an id, so a label that happens to equal some option's id does not pick that
		// option. One naming an id must match that id: falling back to a label match would let a
		// stale id silently become some other option.
		if ( $id === null ) {
			/** @var string $label */
			return $this->resultFor( $this->findByLabel( $property, $label ), $raw, $label );
		}

		$matched = $this->findById( $property, $id );

		if ( $matched !== null && $label !== null && $matched->normalizedLabel() !== SelectOption::normalizeLabel( $label ) ) {
			return NormalizationResult::unresolvable(
				$raw,
				$this->violation( 'select-id-label-mismatch', [ $id, $label ] )
			);
		}

		return $this->resultFor( $matched, $raw, $id );
	}

	/**
	 * @param array<mixed> $raw
	 */
	private function nonStringMemberViolation( array $raw ): ?Violation {
		// A member that is present but not a string is a malformed value, not an absent member:
		// ignoring it would let `{ "id": 42, "label": "Draft" }` resolve by label and hide the
		// caller's bug. Null still counts as absent.
		foreach ( [ 'id', 'label' ] as $member ) {
			if ( isset( $raw[$member] ) && !is_string( $raw[$member] ) ) {
				return $this->violation( 'select-id-label-not-strings' );
			}
		}

		return null;
	}

	/**
	 * The matched option's id, or, when nothing matched, the raw input with an `invalid-option`
	 * violation naming `$offending`.
	 */
	private function resultFor( ?SelectOption $matched, mixed $raw, string $offending ): NormalizationResult {
		return $matched === null
			? NormalizationResult::unresolvable( $raw, $this->violation( 'invalid-option', [ $offending ] ) )
			: NormalizationResult::normalized( $matched->getId() );
	}

	/**
	 * No property name (the caller attaches it) and severity fixed at error; {@see NormalizationResult}
	 * says why.
	 */
	private function violation( string $code, array $args = [] ): Violation {
		return new Violation( propertyName: null, code: $code, args: $args, severity: Severity::Error );
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
		$normalizedLabel = SelectOption::normalizeLabel( $label );

		foreach ( $property->getOptions() as $option ) {
			if ( $option->normalizedLabel() === $normalizedLabel ) {
				return $option;
			}
		}

		return null;
	}

}
