<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
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
		];
	}

	/**
	 * Type-specific fields beyond the common core (type/description/required/default).
	 *
	 * @internal Public only so that PHP-side serializers (REST `toJson`, the Lua
	 * `SchemaLuaSerializer`, and persistence) can share one extension point per type.
	 * Not intended as a general UI-layer API.
	 */
	abstract public function nonCoreToJson(): array;

	/**
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
		);

		// The extension owning the type is disabled or failed to load. Preserve the property
		// (incl. its object-form constraint severities) so the Schema still serves it and a
		// re-save does not drop it; it never validates, so the severity map is inert here.
		// It is built from the normalized $values (not raw $json) so toJson re-wraps every
		// annotated key through the same apply() path as registered types.
		if ( $propertyType === null ) {
			return UnregisteredTypeProperty::fromPartialJson( $propertyCore, $values );
		}

		try {
			return $propertyType->buildPropertyDefinitionFromJson( $propertyCore, $values );
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( 'Invalid property definition: ' . json_encode( $json ), 0, $e );
		}
	}

}
