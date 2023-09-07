<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use InvalidArgumentException;

class ValueFormatRegistry {

	/**
	 * @var array<string, ValueFormatDefinition> Keys are format names
	 */
	private array $formats = [];

	public function registerFormat( ValueFormatDefinition $format ): void {
		$this->formats[$format->getFormatName()] = $format;
	}

	public function getFormat( string $formatName ): ValueFormatDefinition {
		if ( !isset( $this->formats[$formatName] ) ) {
			throw new InvalidArgumentException( "Unknown format: $formatName" );
		}

		return $this->formats[$formatName];
	}

}
