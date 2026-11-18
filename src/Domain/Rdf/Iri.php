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

	private const string UCSCHAR = '\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}'
		. '\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}'
		. '\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}'
		. '\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}';
	private const string IPRIVATE = '\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}';
	private const string UNRESERVED = 'A-Za-z0-9\-._\~';
	private const string SUB_DELIMS = '!$&\'()*+,;=';
	private const string UNRESERVED_OR_SUB_DELIM = self::UNRESERVED . self::UCSCHAR . self::SUB_DELIMS;
	private const string PCT_ENCODED = '%[0-9A-Fa-f]{2}';
	private const string IPCHAR = '(?:[' . self::UNRESERVED_OR_SUB_DELIM . ':@]|' . self::PCT_ENCODED . ')';

	private const string SCHEME = '[A-Za-z][A-Za-z0-9+.\-]*';
	private const string USERINFO = '(?:[' . self::UNRESERVED_OR_SUB_DELIM . ':]|' . self::PCT_ENCODED . ')*+';
	private const string HOST = '(?:\[(?:(?<ipv6>[0-9A-Fa-f:.]+)|v[0-9A-Fa-f]+\.[' . self::UNRESERVED . self::SUB_DELIMS . ':]+)]'
		. '|(?:[' . self::UNRESERVED_OR_SUB_DELIM . ']|' . self::PCT_ENCODED . ')*+)';
	private const string PATH = '(?:' . self::IPCHAR . '|/)*+';
	private const string QUERY = '(?:' . self::IPCHAR . '|[/?' . self::IPRIVATE . '])*+';
	private const string FRAGMENT = '(?:' . self::IPCHAR . '|[/?])*+';

	private const string IRI_PATTERN = '~^' . self::SCHEME . ':'
		. '(?://(?:' . self::USERINFO . '@)?' . self::HOST . '(?::[0-9]*+)?(?:/' . self::PATH . ')?|(?!//)' . self::PATH . ')'
		. '(?:\?' . self::QUERY . ')?(?:#' . self::FRAGMENT . ')?$~uD';

	public string $value;

	public function __construct( string $value ) {
		if ( trim( $value ) === '' ) {
			throw new InvalidArgumentException( 'IRI cannot be empty' );
		}

		$this->value = $value;
	}

	/**
	 * Whether the string is safe to place raw between the angle brackets of an RDF IRI: it has a scheme and
	 * contains no IRIREF-illegal character. This is a security boundary (the #1029 lesson) — an unsafe value
	 * must be rejected, never escaped, so it can never break out of its IRI or forge extra triples.
	 */
	public static function isSafeAbsolute( string $value ): bool {
		if ( preg_match( '/^' . self::SCHEME . ':/', $value ) !== 1 ) {
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

	/**
	 * Whether the string matches RFC 3987's IRI grammar, which strict SPARQL stores such as Oxigraph require.
	 * Every valid IRI is also safe ({@see isSafeAbsolute}).
	 */
	public static function isValid( string $value ): bool {
		if ( preg_match( self::IRI_PATTERN, $value, $parts, PREG_UNMATCHED_AS_NULL ) !== 1 ) {
			return false;
		}

		return $parts['ipv6'] === null || filter_var( $parts['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false;
	}

	public function equals( RdfTerm $other ): bool {
		return $other instanceof self && $this->value === $other->value;
	}

}
