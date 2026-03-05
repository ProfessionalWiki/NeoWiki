<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\View;

use InvalidArgumentException;

readonly class ViewName {

	public function __construct(
		private string $text,
	) {
		if ( trim( $this->text ) === '' ) {
			throw new InvalidArgumentException( 'View name cannot be empty' );
		}
	}

	public function getText(): string {
		return $this->text;
	}

}
