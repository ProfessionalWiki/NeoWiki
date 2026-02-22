<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;

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
		$displayAttributes = $this->displayAttributesToJson();

		return [
			'type' => $this->getPropertyType(),
			'description' => $this->getDescription(),
			'required' => $this->isRequired(),
			'default' => $this->getDefault(),
			'constraints' => $this->constraintsToJson(),
			'displayAttributes' => $displayAttributes === [] ? (object)[] : $displayAttributes,
		];
	}

	abstract protected function constraintsToJson(): array;

	abstract protected function displayAttributesToJson(): array;

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromJson( array $json, PropertyTypeLookup $propertyTypeLookup ): self {
		$propertyType = $propertyTypeLookup->getType( $json['type'] );

		if ( $propertyType === null ) {
			throw new InvalidArgumentException( 'Unknown property type: ' . $json['type'] );
		}

		$propertyCore = new PropertyCore(
			description: $json['description'] ?? '',
			required: $json['required'] ?? false,
			default: $json['default'] ?? null
		);

		$constraints = $json['constraints'] ?? [];
		$displayAttributes = $json['displayAttributes'] ?? [];
		$typeSpecific = array_merge( $json, $constraints, $displayAttributes );

		try {
			return $propertyType->buildPropertyDefinitionFromJson( $propertyCore, $typeSpecific );
		} catch ( \Throwable $e ) {
			throw new InvalidArgumentException( 'Invalid property definition: ' . json_encode( $json ), 0, $e );
		}
	}

}
