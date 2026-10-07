<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * Which stretches of a Subject's search text the search matched: from the terms the engine reports,
 * or guessed from the query when it reports none.
 */
readonly class SearchTermMatcher {

	private function __construct(
		private string $pattern
	) {
	}

	/**
	 * Null when the engine named no terms.
	 *
	 * @param string[] $terms As SqlSearchResult::getTermMatches() returns them: regex fragments,
	 *   already escaped and fenced with word boundaries
	 */
	public static function fromEngineTerms( array $terms ): ?self {
		$terms = array_filter( $terms, static fn ( string $term ): bool => $term !== '' );

		return $terms === [] ? null : new self( '/(' . implode( '|', $terms ) . ')/ui' );
	}

	/**
	 * For an engine that names no terms, CirrusSearch above all: a query word matches any word
	 * starting with it, which covers stems and plurals. Compared against the stored value, so a hit
	 * the engine found through folding goes unattributed. Null when the query holds no usable word.
	 */
	public static function fromQuery( string $query ): ?self {
		$words = self::wordsOf( $query );

		if ( $words === [] ) {
			return null;
		}

		// Words are letters and digits only, so they need no escaping.
		return new self( '/((?<![\p{L}\p{N}])(?:' . implode( '|', $words ) . ')[\p{L}\p{N}]*)/ui' );
	}

	/**
	 * Shorter words are articles and prepositions, which start too many values to attribute a match.
	 */
	private const int MIN_WORD_LENGTH = 3;

	/**
	 * The query's words without its syntax: phrase quotes, `keyword:` operators and excluded terms.
	 *
	 * @return string[]
	 */
	private static function wordsOf( string $query ): array {
		$words = [];

		foreach ( self::split( '/\s+/u', $query ) as $term ) {
			if ( str_starts_with( $term, '-' ) ) {
				continue;
			}

			$value = preg_replace( '/^[\p{L}_]+:/u', '', $term ) ?? $term;

			foreach ( self::split( '/[^\p{L}\p{N}]+/u', $value ) as $word ) {
				if ( mb_strlen( $word ) >= self::MIN_WORD_LENGTH ) {
					$words[] = $word;
				}
			}
		}

		return array_values( array_unique( $words ) );
	}

	/**
	 * @return string[]
	 */
	private static function split( string $pattern, string $text ): array {
		$parts = preg_split( $pattern, $text, -1, PREG_SPLIT_NO_EMPTY );

		return $parts === false ? [] : $parts;
	}

	/**
	 * Unmatched and matched text alternating, unmatched first: even indexes lie outside a match, odd
	 * indexes inside one.
	 *
	 * @return string[]
	 */
	public function splitOnMatches( string $text ): array {
		$parts = preg_split( $this->pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		return $parts === false ? [ $text ] : $parts;
	}

}
