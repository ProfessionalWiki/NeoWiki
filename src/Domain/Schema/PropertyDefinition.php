<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\SeverityNormalizer;
use Throwable;

abstract class PropertyDefinition {

	public function __construct(
		private readonly PropertyCore $core,
	) {
	}

	abstract public function getPropertyType(): string;

	public function getDescription(): string {
		return $this->core->description;
	}

	/**
	 * @return array<string, string> Keyed by lowercase language tag
	 */
	public function getLabels(): array {
		return $this->core->labels;
	}

	public function isRequired(): bool {
		return $this->core->required;
	}

	public function severityOf( string $constraint ): Severity {
		return $this->core->constraintSeverities[$constraint] ?? Severity::Warning;
	}

	public function getDefault(): mixed {
		return $this->core->default;
	}

	public function hasDefault(): bool {
		return $this->core->default !== null;
	}

	public function allowsMultipleValues(): bool {
		return false;
	}

	public function toJson(): array {
		return SeverityNormalizer::apply(
			array_merge( $this->coreToJson(), $this->nonCoreToJson() ),
			$this->core->constraintSeverities
		);
	}

	/**
	 * The fields common to every Property Type.
	 */
	protected function coreToJson(): array {
		return [
			'type' => $this->getPropertyType(),
			'description' => $this->getDescription(),
			'required' => $this->isRequired(),
			'default' => $this->getDefault(),
		] + ( $this->getLabels() === [] ? [] : [ 'labels' => $this->getLabels() ] );
	}

	/**
	 * Type-specific fields beyond the common core (type/description/labels/required/default).
	 *
	 * @internal Public only so that PHP-side serializers (REST `toJson`, the Lua
	 * `SchemaLuaSerializer`, and persistence) can share one extension point per type.
	 * Not intended as a general UI-layer API.
	 */
	abstract public function nonCoreToJson(): array;

	/**
	 * JSON Schema (draft 2020-12) for the value of a Statement of this property: what the Property
	 * Type's validate() checks about the value itself, in a form standard validators apply. `true`
	 * when nothing is known about the value.
	 *
	 * @return array<string, mixed>|true
	 */
	abstract public function toJsonSchema(): array|true;

	/**
	 * JSON Schema for a value held as a list of parts: the schema of one part, plus how many parts
	 * this definition admits.
	 *
	 * @param array<string, mixed>|bool $partSchema
	 *
	 * @return array<string, mixed>
	 */
	protected function listValueSchema( array|bool $partSchema ): array {
		return [ 'type' => 'array', 'items' => $partSchema ]
			+ ( $this->allowsMultipleValues() ? [] : [ 'maxItems' => 1 ] )
			+ ( $this->isRequired() ? [ 'minItems' => 1 ] : [] );
	}

	/**
	 * @throws InvalidFieldException When the Property Type cannot use the value of a field, saying which.
	 * @throws InvalidArgumentException
	 */
	public static function fromJson( array $json, PropertyTypeLookup $propertyTypeLookup ): self {
		// A definition without a usable type name is structurally invalid, as opposed to
		// merely referencing a type no extension registered. Callers skip it; without
		// this guard the lookup below fails with a TypeError they do not catch.
		if ( !array_key_exists( 'type', $json ) || !is_string( $json['type'] ) || $json['type'] === '' ) {
			throw new InvalidArgumentException( 'Property definition needs a non-empty type: ' . json_encode( $json ) );
		}

		[ $values, $severities ] = SeverityNormalizer::extract( $json );

		/** @var string $description */
		$description = $values['description'] ?? '';
		/** @var bool $required */
		$required = $values['required'] ?? false;

		$propertyType = $propertyTypeLookup->getType( $json['type'] );

		// Severity is a Constraint concept. Display Attributes are explicitly not Constraints
		// (they are overridable per Layout), so a severity on one is meaningless. Drop it:
		// left in the map it would make toJson re-emit the attribute as an object and break
		// every consumer that reads it as a scalar.
		if ( $propertyType !== null ) {
			foreach ( $propertyType->getDisplayAttributeNames() as $displayAttribute ) {
				unset( $severities[$displayAttribute] );
			}
		}

		$propertyCore = new PropertyCore(
			description: $description,
			required: $required,
			default: $values['default'] ?? null,
			constraintSeverities: $severities,
			labels: self::labelsFromJson( $values['labels'] ?? [] ),
		);

		// The extension owning the type is disabled or failed to load. Preserve the property
		// (incl. its object-form constraint severities) so the Schema still serves it and a
		// re-save does not drop it; it never validates, so the severity map is inert here.
		// It is built from the normalized $values (not raw $json) so toJson re-wraps every
		// annotated key through the same apply() path as registered types.
		if ( $propertyType === null ) {
			return UnregisteredTypeProperty::fromPartialJson( $propertyCore, $values );
		}

		return self::buildWithPropertyType( $propertyType, $propertyCore, $values, $json );
	}

	/**
	 * @return array<string, string>
	 */
	private static function labelsFromJson( mixed $json ): array {
		$labels = [];

		foreach ( is_array( $json ) ? $json : [] as $language => $label ) {
			if ( is_string( $label ) && trim( $label ) !== '' ) {
				$labels[strtolower( (string)$language )] = trim( $label );
			}
		}

		return $labels;
	}

	/**
	 * @throws InvalidFieldException
	 * @throws InvalidArgumentException
	 */
	private static function buildWithPropertyType(
		PropertyType $propertyType,
		PropertyCore $propertyCore,
		array $values,
		array $json
	): self {
		try {
			return $propertyType->buildPropertyDefinitionFromJson( $propertyCore, $values );
		} catch ( InvalidFieldException $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( 'Invalid property definition: ' . json_encode( $json ), 0, $e );
		}
	}

	/**
	 * @throws InvalidFieldException
	 */
	protected static function wholeNumberOrNull( array $json, string $field ): ?int {
		$value = $json[$field] ?? null;

		if ( $value === null || is_int( $value ) ) {
			return $value;
		}

		throw InvalidFieldException::mustBe( $field, 'a whole number', $value );
	}

	/**
	 * @param string $expected What the field must hold, as in "must be $expected".
	 *
	 * @throws InvalidFieldException
	 */
	protected static function stringOrNull( array $json, string $field, string $expected ): ?string {
		$value = $json[$field] ?? null;

		if ( $value === null || is_string( $value ) ) {
			return $value;
		}

		throw InvalidFieldException::mustBe( $field, $expected, $value );
	}

}
