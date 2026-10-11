<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\WikiConfig;

use ProfessionalWiki\NeoWiki\Domain\LanguageTag;

/**
 * The shape of value an exposed setting takes: which values are valid, and how the on-page
 * reference and the save-time error describe it.
 */
enum ConfigValueType {

	case Boolean;
	case LanguageList;

	public function isValidValue( mixed $value ): bool {
		return match ( $this ) {
			self::Boolean => is_bool( $value ),
			self::LanguageList => self::isLanguageList( $value ),
		};
	}

	/**
	 * What a setting of this type comes down to when no value it accepts is configured: off, or no
	 * languages, which stands for the content language.
	 */
	public function fallbackValue(): mixed {
		return match ( $this ) {
			self::Boolean => false,
			self::LanguageList => [],
		};
	}

	/**
	 * The accepted-value description for the on-page reference.
	 *
	 * @return array A message spec: [ messageKey, ...params ].
	 */
	public function describe(): array {
		return match ( $this ) {
			self::Boolean => [ 'neowiki-config-type-boolean' ],
			self::LanguageList => [ 'neowiki-config-type-language-list' ],
		};
	}

	/**
	 * The save-time error for a value of the wrong shape.
	 *
	 * @return array A message spec: [ messageKey, ...params ].
	 */
	public function invalidValueError( string $pageKey ): array {
		return match ( $this ) {
			self::Boolean => [ 'neowiki-config-error-invalid-boolean', $pageKey ],
			self::LanguageList => [ 'neowiki-config-error-invalid-language-list', $pageKey ],
		};
	}

	private static function isLanguageList( mixed $value ): bool {
		if ( !is_array( $value ) || !array_is_list( $value ) ) {
			return false;
		}

		foreach ( $value as $tag ) {
			if ( !is_string( $tag ) || !LanguageTag::isValid( $tag ) ) {
				return false;
			}
		}

		return true;
	}

}
