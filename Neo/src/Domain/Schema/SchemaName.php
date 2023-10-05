<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

use InvalidArgumentException;

class SchemaName {

	private const RESERVED_NAMES = [
		'page',
		'subject'
	];

	public function __construct(
		private readonly string $text,
	) {
		if ( trim( $this->text ) === '' ) {
			throw new InvalidArgumentException( 'Schema name cannot be empty' );
		}

		if ( in_array( strtolower( $text ), self::RESERVED_NAMES ) ) {
			throw new InvalidArgumentException( 'Schema name is reserved' );
		}
	}

	public function getText(): string {
		return $this->text;
	}

}
