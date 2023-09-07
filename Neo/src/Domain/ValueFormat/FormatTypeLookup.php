<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\ValueFormat;

use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

class FormatTypeLookup {

	public function __construct(
		private readonly ValueFormatRegistry $registry
	) {
	}

	public function formatToType( string $format ): ValueType {
		return $this->registry->getFormat( $format )->getValueType();
	}

}
