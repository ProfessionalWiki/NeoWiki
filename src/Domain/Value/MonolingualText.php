<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\LanguageTag;

/**
 * One part of a {@see MonolingualTextValue}: a text and the language it is in.
 *
 * Canonical form is established here, so every reader sees a trimmed text and a lowercase tag.
 */
readonly class MonolingualText {

	public string $text;
	public string $language;

	/**
	 * @throws InvalidArgumentException When the language is not a well-formed BCP 47 tag.
	 */
	public function __construct( string $text, string $language ) {
		if ( !LanguageTag::isValid( $language ) ) {
			throw new InvalidArgumentException( 'Invalid language tag: "' . $language . '".' );
		}

		$this->text = trim( $text );
		$this->language = strtolower( $language );
	}

	/**
	 * @return array{text: string, language: string}
	 */
	public function toScalars(): array {
		return [
			'text' => $this->text,
			'language' => $this->language,
		];
	}

}
