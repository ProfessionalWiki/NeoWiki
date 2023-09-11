<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\CurrencyFormat;

class CurrencyProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly string $currencyCode,
		private readonly float|int|null $precision,
		private readonly float|int|null $minimum,
		private readonly float|int|null $maximum,

	) {
		parent::__construct( $core );
	}

	public function getFormat(): string {
		return CurrencyFormat::NAME;
	}

	public function getCurrencyCode(): string {
		return $this->currencyCode;
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
			currencyCode: $property['currencyCode'],
			precision: $property['precision'] ?? null,
			minimum: $property['minimum'] ?? null,
			maximum: $property['maximum'] ?? null,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'currencyCode' => $this->getCurrencyCode(),
			'precision' => $this->getPrecision(),
			'minimum' => $this->getMinimum(),
			'maximum' => $this->getMaximum(),
		];
	}

}
