<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;

class ColorProperty extends PropertyDefinition {

	private const HEX_COLOR_REGEX = '/^#[0-9a-fA-F]{6}$/';

	/**
	 * @param list<string> $allowedColors
	 */
	public function __construct(
		PropertyCore $core,
		private readonly array $allowedColors,
	) {
		foreach ( $allowedColors as $color ) {
			if ( !is_string( $color ) || preg_match( self::HEX_COLOR_REGEX, $color ) !== 1 ) {
				throw new InvalidArgumentException(
					'ColorProperty allowedColors must be 6-digit hex strings prefixed with #'
				);
			}
		}

		if ( $core->default !== null && ( !is_string( $core->default ) || preg_match( self::HEX_COLOR_REGEX, $core->default ) !== 1 ) ) {
			throw new InvalidArgumentException(
				'ColorProperty default must be a 6-digit hex string prefixed with #'
			);
		}

		parent::__construct( $core );
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
			throw new InvalidArgumentException( 'ColorProperty allowedColors must be an array' );
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

}
