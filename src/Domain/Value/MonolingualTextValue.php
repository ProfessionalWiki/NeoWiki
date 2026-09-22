<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

use TypeError;

readonly class MonolingualTextValue implements NeoValue {

	/**
	 * @var MonolingualText[]
	 */
	public array $parts;

	public function __construct( MonolingualText ...$parts ) {
		$this->parts = array_values(
			array_filter( $parts, static fn ( MonolingualText $part ): bool => $part->text !== '' )
		);
	}

	/**
	 * The inverse of {@see toScalars()}. Each part carries its own language, so a bare string would
	 * have nowhere to put it; a part that is not an object with a string text and a string language
	 * is raised as a TypeError, which the deserializers report as bad input.
	 *
	 * @throws TypeError
	 */
	public static function fromScalars( mixed $json ): self {
		$parts = [];

		foreach ( (array)$json as $part ) {
			if ( !is_array( $part ) || !is_string( $part['text'] ?? null ) || !is_string( $part['language'] ?? null ) ) {
				throw new TypeError( 'Monolingual text entry must have a string text and a string language' );
			}

			$parts[] = new MonolingualText( $part['text'], $part['language'] );
		}

		return new self( ...$parts );
	}

	/**
	 * @return list<string>
	 */
	public function getTexts(): array {
		return array_map(
			static fn ( MonolingualText $part ): string => $part->text,
			$this->parts
		);
	}

	public function getType(): ValueType {
		return ValueType::MonolingualText;
	}

	/**
	 * @return list<array{text: string, language: string}>
	 */
	public function toScalars(): array {
		return array_map(
			static fn ( MonolingualText $part ): array => $part->toScalars(),
			$this->parts
		);
	}

	public function isEmpty(): bool {
		return $this->parts === [];
	}

}
