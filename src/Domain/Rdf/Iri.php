<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Rdf;

use InvalidArgumentException;

/**
 * An absolute IRI. Stores the full IRI string; prefix abbreviation is a serialization concern
 * handled by the {@see RdfSerializer}, so this value object never carries a prefixed name.
 */
readonly class Iri implements RdfTerm {

	// The characters an RDF IRIREF may not contain: the ASCII specials plus backtick and backslash.
	// Space and the control characters are handled by the code-point check in isSafeAbsolute().
	private const string ILLEGAL_CHARS = '<>"{}|^`\\';

	public string $value;

	public function __construct( string $value ) {
		if ( trim( $value ) === '' ) {
			throw new InvalidArgumentException( 'IRI cannot be empty' );
		}

		$this->value = $value;
	}

	/**
	 * Whether the string is a syntactically valid absolute IRI safe to place raw in an RDF document: it
	 * has a scheme and contains no IRIREF-illegal character. This is a security boundary (the #1029
	 * lesson) — an unsafe value must be rejected, never escaped, so it can never break out of its IRI or
	 * forge extra triples. Used when deciding whether a value is an IRI object rather than a literal, and
	 * (via {@see CurieExpander}) to validate authored Mapping terms and prefix namespaces.
	 */
	public static function isSafeAbsolute( string $value ): bool {
		if ( preg_match( '/^[A-Za-z][A-Za-z0-9+.\-]*:/', $value ) !== 1 ) {
			return false;
		}

		foreach ( str_split( $value ) as $byte ) {
			$code = ord( $byte );

			// Controls (0x00–0x20, incl. space) and DEL are never allowed; nor are the specials. Bytes
			// >= 0x80 are UTF-8 continuation of raw Unicode and pass through, keeping IRIs readable.
			if ( $code <= 0x20 || $code === 0x7F || str_contains( self::ILLEGAL_CHARS, $byte ) ) {
				return false;
			}
		}

		return true;
	}

	public function equals( RdfTerm $other ): bool {
		return $other instanceof self && $this->value === $other->value;
	}

}
