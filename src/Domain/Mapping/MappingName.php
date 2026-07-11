<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

use InvalidArgumentException;

readonly class MappingName {

	public function __construct(
		private string $text,
	) {
		if ( trim( $this->text ) === '' ) {
			throw new InvalidArgumentException( 'Mapping name cannot be empty' );
		}
	}

	public function getText(): string {
		return $this->text;
	}

}
