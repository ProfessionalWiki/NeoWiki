<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

readonly class StringValue implements NeoValue {

	/**
	 * @var string[]
	 */
	public array $strings;

	public function __construct( string ...$strings ) {
		$this->strings = array_values(
			array_filter( $strings, static fn ( string $part ): bool => trim( $part ) !== '' )
		);
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
