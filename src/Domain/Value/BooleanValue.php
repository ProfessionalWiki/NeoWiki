<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

readonly class BooleanValue implements NeoValue {

	public function __construct(
		public bool $boolean
	) {
	}

	public function getType(): ValueType {
		return ValueType::Boolean;
	}

	public function toScalars(): bool {
		return $this->boolean;
	}

	public function isEmpty(): bool {
		return false;
	}

}
