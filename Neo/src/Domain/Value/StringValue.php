<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

readonly class StringValue implements NeoValue {

	/**
	 * @var string[]
	 */
	public array $strings;

	public function __construct( string ...$strings ) {
		$this->strings = $strings;
	}

	public function getType(): ValueType {
		return ValueType::String;
	}

	public function toScalars(): array {
		return $this->strings;
	}

	public function isEmpty(): bool {
		return $this->strings === [];
	}

}
