<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

class BooleanValue implements NeoValue {

	public function __construct(
		public readonly bool $boolean
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
