<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Mapping;

use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;

/**
 * Expands a Mapping term — a CURIE (`prefix:local` against the Mapping's declared prefixes) or an
 * absolute IRI — into an {@see Iri}, or returns null when it cannot be resolved safely.
 *
 * This is a security boundary (the #1029 lesson). Mapping terms are user-authored JSON that end up as
 * class, predicate, and datatype IRIs in the projected RDF. An unsafe term is **rejected**, never
 * escaped: a Mapping must reproduce the ontology's exact term, so a percent-encoded variant would
 * silently point at the wrong thing. The expanded IRI must therefore be a syntactically valid absolute
 * IRI containing none of the IRIREF-illegal characters, or the term is unusable. The same expander is
 * used by the content validator (to reject bad Mappings at save time) and by the projector (to expand
 * at projection time), so a stored Mapping can never inject triples into the output document.
 */
readonly class CurieExpander {

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI.
	 */
	public function __construct(
		private array $prefixes,
	) {
	}

	public function expand( string $term ): ?Iri {
		$colon = strpos( $term, ':' );

		if ( $colon === false ) {
			return null;
		}

		$prefix = substr( $term, 0, $colon );

		if ( array_key_exists( $prefix, $this->prefixes ) ) {
			return self::newSafeIri( $this->prefixes[$prefix] . substr( $term, $colon + 1 ) );
		}

		// Not a declared CURIE. Accept only an explicit absolute IRI with an authority (`scheme://…`);
		// a bare `prefix:local` with an undeclared prefix is a typo'd CURIE and is rejected, not
		// silently treated as an IRI. Non-authority schemes (urn:, mailto:) are out of scope for v1.
		if ( str_contains( $term, '://' ) ) {
			return self::newSafeIri( $term );
		}

		return null;
	}

	private static function newSafeIri( string $iri ): ?Iri {
		return self::isSafeAbsoluteIri( $iri ) ? new Iri( $iri ) : null;
	}

	/**
	 * Whether the string is a safe CURIE prefix label to place raw in an RDF `@prefix` declaration: a
	 * letter followed by letters, digits, `_` or `-` (the save-time label grammar in
	 * mappingContentSchema.json). Like the namespace, the label reaches the serializer's prefix table,
	 * so a label containing whitespace, a colon, or an angle bracket would break out of the `@prefix`
	 * line and inject triples. Save validation already rejects such labels; this guards the projection
	 * path against a Mapping stored outside it (import, a pre-validation page).
	 */
	public static function isValidPrefixLabel( string $label ): bool {
		return preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $label ) === 1;
	}

	/**
	 * Whether the string is a syntactically valid absolute IRI safe to place raw in an RDF document.
	 * Used for prefix namespace IRIs too, which reach the serializer's prefix table and so are an
	 * injection vector even when unused. The check itself lives on the {@see Iri} value object.
	 */
	public static function isSafeAbsoluteIri( string $iri ): bool {
		return Iri::isSafeAbsolute( $iri );
	}

}
