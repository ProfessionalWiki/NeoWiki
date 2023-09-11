<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use OutOfBoundsException;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class FormatTypeLookup {

	public function __construct(
		private readonly ValueFormatRegistry $registry
	) {
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function formatToType( string $format ): ValueType {
		return $this->registry->getFormatOrThrow( $format )->getValueType();
	}

}
