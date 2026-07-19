<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

use InvalidArgumentException;

readonly class MappingName {

	// The name of the built-in native projection (RdfPageProjector::PROJECTION), which is not backed by a
	// Mapping page. Reserving it here — mirroring SchemaName's reserved names — keeps a Mapping page from
	// shadowing the native projection, and closes the gap where such a page would save but stay unreachable.
	private const array RESERVED_NAMES = [
		'native'
	];

	public function __construct(
		private string $text,
	) {
		if ( trim( $this->text ) === '' ) {
			throw new InvalidArgumentException( 'Mapping name cannot be empty' );
		}

		if ( in_array( strtolower( $text ), self::RESERVED_NAMES ) ) {
			throw new InvalidArgumentException( 'Mapping name is reserved' );
		}
	}

	public function getText(): string {
		return $this->text;
	}

}
