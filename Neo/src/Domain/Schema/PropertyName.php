<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

readonly class PropertyName {

	public function __construct(
		public string $text
	) {
		if ( $text === '' ) {
			throw new \InvalidArgumentException( 'Property name cannot be empty' );
		}
	}

	public function __toString(): string {
		return $this->text;
	}

}
