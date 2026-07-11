<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Rdf;

use pietercolpaert\hardf\TriGParser;

/**
 * Test helper: parses an RDF document (TriG or Turtle) with hardf and returns its quads as a sorted
 * set of canonical strings. Because both the expected document and the projector's output are run
 * through the same parser, cosmetic differences (prefix use, the GRAPH keyword, bare vs typed numeric
 * literals, statement order) collapse, so comparisons assert on the set of quads, not on formatting.
 */
final class ParsedRdf {

	/**
	 * @return list<string> Sorted "subject \t predicate \t object \t graph" lines, one per quad.
	 */
	public static function canonicalQuads( string $rdf ): array {
		$triples = ( new TriGParser() )->parse( $rdf );

		$lines = array_map(
			static fn( array $triple ): string => implode( "\t", [
				self::term( $triple['subject'] ),
				self::term( $triple['predicate'] ),
				self::term( $triple['object'] ),
				self::term( $triple['graph'] ?? '' ),
			] ),
			$triples
		);

		sort( $lines );

		return $lines;
	}

	private static function term( mixed $term ): string {
		// The native projection never emits RDF 1.2 triple terms, so every term parses to a string.
		return is_string( $term ) ? $term : json_encode( $term );
	}

}
