<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use InvalidArgumentException;

/**
 * An absolute IRI. Stores the full IRI string; prefix abbreviation is a serialization concern
 * handled by the {@see RdfSerializer}, so this value object never carries a prefixed name.
 */
readonly class Iri implements RdfTerm {

	public string $value;

	public function __construct( string $value ) {
		if ( trim( $value ) === '' ) {
			throw new InvalidArgumentException( 'IRI cannot be empty' );
		}

		$this->value = $value;
	}

	public function equals( RdfTerm $other ): bool {
		return $other instanceof self && $this->value === $other->value;
	}

}
