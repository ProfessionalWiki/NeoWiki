<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use OutOfBoundsException;

interface ValueFormatLookup {

	public function getFormat( string $formatName ): ?ValueFormat;

	/**
	 * @throws OutOfBoundsException
	 */
	public function getFormatOrThrow( string $formatName ): ValueFormat;

}
