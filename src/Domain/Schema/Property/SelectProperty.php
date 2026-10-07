<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;

class SelectProperty extends PropertyDefinition {

	/**
	 * @param SelectOption[] $options
	 */
	public function __construct(
		PropertyCore $core,
		private readonly array $options,
		private readonly bool $multiple,
	) {
		parent::__construct( $core );
		$this->assertUniqueIds();
		$this->assertUniqueLabels();
	}

	public function getPropertyType(): string {
		return SelectType::NAME;
	}

	/**
	 * @return SelectOption[]
	 */
	public function getOptions(): array {
		return $this->options;
	}

	/**
	 * @return string[]
	 */
	public function getOptionIds(): array {
		return array_map( fn( SelectOption $o ): string => $o->getId(), $this->options );
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$rawOptions = $property['options'] ?? [];

		if ( !is_array( $rawOptions ) ) {
			throw new InvalidArgumentException( 'Select options must be an array' );
		}

		$options = array_map(
			fn( mixed $raw ): SelectOption => SelectOption::fromJson( $raw ),
			array_values( $rawOptions )
		);

		return new self(
			core: $core,
			options: $options,
			multiple: $property['multiple'] ?? false,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'options' => array_map( fn( SelectOption $o ): array => $o->toJson(), $this->options ),
			'multiple' => $this->allowsMultipleValues(),
		];
	}

	public function toJsonSchema(): array {
		$ids = $this->getOptionIds();

		// Without options no value is valid, which `items: false` says and an empty enum may not.
		return $this->listValueSchema( $ids === [] ? false : [ 'enum' => $ids ] );
	}

	private function assertUniqueIds(): void {
		$ids = $this->getOptionIds();

		if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
			throw new InvalidArgumentException( 'Select option ids must be unique' );
		}
	}

	private function assertUniqueLabels(): void {
		$labels = array_map( fn( SelectOption $o ): string => $o->normalizedLabel(), $this->options );

		if ( count( $labels ) !== count( array_unique( $labels ) ) ) {
			throw new InvalidArgumentException( 'Select option labels must be unique' );
		}
	}

}
