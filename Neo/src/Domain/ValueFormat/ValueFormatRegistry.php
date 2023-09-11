<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use OutOfBoundsException;

class ValueFormatRegistry implements ValueFormatLookup {

	/**
	 * @var array<string, ValueFormat> Keys are format names
	 */
	private array $formats = [];

	public function registerFormat( ValueFormat $format ): void {
		$this->formats[$format->getFormatName()] = $format;
	}

	public function getFormat( string $formatName ): ?ValueFormat {
		return $this->formats[$formatName] ?? null;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getFormatOrThrow( string $formatName ): ValueFormat {
		$format = $this->getFormat( $formatName );

		if ( $format === null ) {
			throw new OutOfBoundsException( "Unknown format: $formatName" );
		}

		return $format;
	}

}
