<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

readonly class PropertyName {

	public string $text;

	public function __construct( string $text ) {
		$trimmed = trim( $text );

		if ( $trimmed === '' ) {
			throw new InvalidArgumentException( 'Property name cannot be empty' );
		}

		$this->text = $trimmed;
	}

	public function __toString(): string {
		return $this->text;
	}

}
