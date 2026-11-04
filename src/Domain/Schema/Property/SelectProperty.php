<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException;
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
	 * @return string[]
	 */
	public function getOptionIds(): array {
		return array_map( fn( SelectOption $o ): string => $o->getId(), $this->options );
	}

	public function getOption( string $id ): ?SelectOption {
		foreach ( $this->options as $option ) {
			if ( $option->getId() === $id ) {
				return $option;
			}
		}

		return null;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$rawOptions = $property['options'] ?? [];

		if ( !is_array( $rawOptions ) ) {
			throw InvalidFieldException::mustBe( 'options', 'a list of options', $rawOptions );
		}

		$options = array_map( self::optionFromJson( ... ), array_values( $rawOptions ) );

		return new self(
			core: $core,
			options: $options,
			multiple: $property['multiple'] ?? false,
		);
	}

	private static function optionFromJson( mixed $json ): SelectOption {
		try {
			return SelectOption::fromJson( $json );
		} catch ( InvalidArgumentException ) {
			throw new InvalidFieldException(
				'options',
				'each option needs an ID and a label, both non-empty text, not ' . InvalidFieldException::asJson( $json )
			);
		}
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
		$repeat = self::keyOfFirstRepeat( $this->getOptionIds() );

		if ( $repeat !== null ) {
			throw new InvalidFieldException(
				'options',
				'the option ID ' . InvalidFieldException::asJson( $this->options[$repeat]->getId() ) . ' is used more than once'
			);
		}
	}

	private function assertUniqueLabels(): void {
		$repeat = self::keyOfFirstRepeat( array_map( fn( SelectOption $o ): string => $o->normalizedLabel(), $this->options ) );

		if ( $repeat !== null ) {
			throw new InvalidFieldException(
				'options',
				'the option label ' . InvalidFieldException::asJson( $this->options[$repeat]->getLabel() )
					. ' is used more than once, ignoring letter case and surrounding spaces'
			);
		}
	}

	/**
	 * @param string[] $values
	 */
	private static function keyOfFirstRepeat( array $values ): int|string|null {
		return array_key_first( array_diff_key( $values, array_unique( $values ) ) );
	}

}
