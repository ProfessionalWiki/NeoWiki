<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Layout;

use InvalidArgumentException;

class LayoutName {

	public function __construct(
		private string $text,
	) {
		if ( trim( $this->text ) === '' ) {
			throw new InvalidArgumentException( 'Layout name cannot be empty' );
		}
	}

	public function getText(): string {
		return $this->text;
	}

}
