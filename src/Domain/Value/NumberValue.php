<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

readonly class NumberValue implements NeoValue {

	public function __construct(
		public int|float $number
	) {
	}

	public function getType(): ValueType {
		return ValueType::Number;
	}

	public function toScalars(): int|float {
		return $this->number;
	}

	public function isEmpty(): bool {
		return false;
	}

}
