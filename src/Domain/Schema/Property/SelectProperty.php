<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;

class SelectProperty extends PropertyDefinition {

	/**
	 * @param string[] $options
	 */
	public function __construct(
		PropertyCore $core,
		private readonly array $options,
		private readonly bool $multiple,
	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return SelectType::NAME;
	}

	/**
	 * @return string[]
	 */
	public function getOptions(): array {
		return $this->options;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$options = $property['options'] ?? [];

		if ( !is_array( $options ) ) {
			throw new InvalidArgumentException( 'Select options must be an array' );
		}

		foreach ( $options as $option ) {
			if ( !is_string( $option ) ) {
				throw new InvalidArgumentException( 'Each select option must be a string' );
			}
		}

		return new self(
			core: $core,
			options: array_values( $options ),
			multiple: $property['multiple'] ?? false,
		);
	}

	protected function nonCoreToJson(): array {
		return [
			'options' => $this->getOptions(),
			'multiple' => $this->allowsMultipleValues(),
		];
	}

}
