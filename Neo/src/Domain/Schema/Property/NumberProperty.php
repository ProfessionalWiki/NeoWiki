<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\CurrencyFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\NumberFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\ProgressFormat;

class NumberProperty extends PropertyDefinition {

	public function __construct(
		string $format,
		string $description,
		bool $required,
		?float $default,
		private readonly ?float $minimum,
		private readonly ?float $maximum,
	) {
		$this->assertIsNumberFormat( $format );
		parent::__construct(
			type: ValueType::Number,
			format: $format,
			description: $description,
			required: $required,
			default: $default
		);
	}

	private function assertIsNumberFormat( string $format ): void {
		if ( !in_array( $format, [
			NumberFormat::NAME,
			CurrencyFormat::NAME,
			ProgressFormat::NAME
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
