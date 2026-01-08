<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\NumberFormat;

class NumberProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly float|int|null $precision,
		private readonly float|int|null $minimum,
		private readonly float|int|null $maximum,

	) {
		parent::__construct( $core );
	}

	public function getFormat(): string {
		return NumberFormat::NAME;
	}

	public function getPrecision(): float|int|null {
		return $this->precision;
	}

	public function hasPrecision(): bool {
		return $this->precision !== null;
	}

	public function getMinimum(): float|int|null {
		return $this->minimum;
	}

	public function hasMinimum(): bool {
		return $this->minimum !== null;
	}

	public function getMaximum(): float|int|null {
		return $this->maximum;
	}

	public function hasMaximum(): bool {
		return $this->maximum !== null;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			precision: $property['precision'] ?? null,
			minimum: $property['minimum'] ?? null,
			maximum: $property['maximum'] ?? null,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'precision' => $this->getPrecision(),
			'minimum' => $this->getMinimum(),
			'maximum' => $this->getMaximum(),
		];
	}

}
