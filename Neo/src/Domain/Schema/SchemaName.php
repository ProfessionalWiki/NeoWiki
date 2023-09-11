<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

class SchemaName {

	public function __construct(
		private readonly string $text,
	) {
		if ( trim( $this->text ) === '' ) {
			throw new InvalidArgumentException( 'Schema name cannot be empty' );
		}
	}

	public function getText(): string {
		return $this->text;
	}

}
