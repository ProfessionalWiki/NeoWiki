<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
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
		return array_merge( $this->coreToJson(), $this->nonCoreToJson() );
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

		$propertyType = $propertyTypeLookup->getType( $json['type'] );

		$propertyCore = new PropertyCore(
			description: $json['description'] ?? '',
			required: $json['required'] ?? false,
			default: $json['default'] ?? null
		);

		// The extension owning the type is disabled or failed to load. Preserve the
		// definition instead of dropping it, so the rest of the Schema keeps working
		// and the property survives a re-save.
		if ( $propertyType === null ) {
			return UnregisteredTypeProperty::fromPartialJson( $propertyCore, $json );
		}

		try {
			return $propertyType->buildPropertyDefinitionFromJson( $propertyCore, $json );
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( 'Invalid property definition: ' . json_encode( $json ), 0, $e );
		}
	}

}
