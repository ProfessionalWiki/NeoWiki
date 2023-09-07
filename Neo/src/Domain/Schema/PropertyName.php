<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

class PropertyName {

	public function __construct(
		public readonly string $text
	) {
		if ( $text === '' ) {
			throw new \InvalidArgumentException( 'Property name cannot be empty' );
		}
	}

	public function __toString(): string {
		return $this->text;
	}

}
