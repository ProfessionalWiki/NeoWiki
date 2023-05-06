<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueType;

class NumberProperty extends PropertyDefinition {

	public function __construct(
		ValueFormat $format,
		string $description,
		private readonly ?float $minimum,
		private readonly ?float $maximum,
	) {
		$this->assertIsNumberFormat( $format );

		parent::__construct(
			type: ValueType::Number,
			format: $format,
			description: $description
		);
	}

	private function assertIsNumberFormat( ValueFormat $format ): void {
		if ( !in_array( $format, [
			ValueFormat::Number,
			ValueFormat::Percentage,
			ValueFormat::Currency,
			ValueFormat::Slider,
		] ) ) {
			throw new InvalidArgumentException( 'NumberProperty must have a number format' );
		}
	}

	public function getMinimum(): ?float {
		return $this->minimum;
	}

	public function getMaximum(): ?float {
		return $this->maximum;
	}

}
