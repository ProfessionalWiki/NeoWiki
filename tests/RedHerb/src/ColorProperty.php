<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\Schema\InvalidFieldException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class ColorProperty extends PropertyDefinition {

	/**
	 * Carries no delimiters and stays within ECMA-262, so that the JSON Schema documents can use it
	 * as a `pattern`.
	 */
	public const string HEX_COLOR_PATTERN = '^#[0-9a-fA-F]{6}$';

	public const string HEX_COLOR_REGEX = '/' . self::HEX_COLOR_PATTERN . '/';

	/**
	 * @param list<string> $allowedColors
	 */
	public function __construct(
		PropertyCore $core,
		private readonly array $allowedColors,
	) {
		foreach ( $allowedColors as $color ) {
			if ( !self::isValidHex( $color ) ) {
				throw InvalidFieldException::mustBe( 'allowedColors', 'a list of colors in the form #RRGGBB', $allowedColors );
			}
		}

		if ( $core->default !== null && !self::isValidHex( $core->default ) ) {
			throw InvalidFieldException::mustBe( 'default', 'a color in the form #RRGGBB', $core->default );
		}

		parent::__construct( $core );
	}

	private static function isValidHex( mixed $value ): bool {
		return is_string( $value ) && preg_match( self::HEX_COLOR_REGEX, $value ) === 1;
	}

	public function getPropertyType(): string {
		return ColorType::NAME;
	}

	/**
	 * @return list<string>
	 */
	public function getAllowedColors(): array {
		return $this->allowedColors;
	}

	public function hasAllowedColors(): bool {
		return $this->allowedColors !== [];
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$allowedColors = $property['allowedColors'] ?? [];

		if ( !is_array( $allowedColors ) ) {
			throw InvalidFieldException::mustBe( 'allowedColors', 'a list of colors in the form #RRGGBB', $allowedColors );
		}

		return new self(
			core: $core,
			allowedColors: array_values( $allowedColors ),
		);
	}

	public function nonCoreToJson(): array {
		return [
			'allowedColors' => $this->allowedColors,
		];
	}

	public function toJsonSchema(): array {
		$part = [ 'type' => 'string', 'pattern' => self::HEX_COLOR_PATTERN ];

		if ( $this->hasAllowedColors() ) {
			$part['enum'] = $this->allowedColors;
		}

		return $this->listValueSchema( $part );
	}

}
