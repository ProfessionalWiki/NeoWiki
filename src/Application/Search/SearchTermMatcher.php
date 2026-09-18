<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Search;

/**
 * Which parts of a searchable string the search matched, so a Subject can be held responsible for a
 * hit and the reader shown why. Each search engine says what it matched in its own way, so there is
 * a way to build this from each.
 */
readonly class SearchTermMatcher {

	private function __construct(
		private string $pattern
	) {
	}

	/**
	 * What MediaWiki's own database search engines matched. They hand each hit the regex fragments
	 * they built from the query - escaped and fenced with word boundaries by
	 * SearchDatabase::regexTerm() - and joining them gives the pattern SearchHighlighter marks the
	 * page's own text up with, so a Subject line is called matched on exactly the terms the page was
	 * found by.
	 *
	 * Null when the engine named no terms: a pattern of nothing matches everywhere.
	 *
	 * @param string[] $terms As SqlSearchResult::getTermMatches() returns them
	 */
	public static function fromEngineTerms( array $terms ): ?self {
		$terms = array_filter( $terms, static fn ( string $term ): bool => $term !== '' );

		return $terms === [] ? null : new self( '/(' . implode( '|', $terms ) . ')/ui' );
	}

	/**
	 * A guess at what an engine that says nothing matched, from the query the reader typed.
	 * CirrusSearch is the one that matters: it hands a search hit no terms at all, so a Subject line
	 * is called matched when it has a word starting with one of the query's, which covers the stemmed
	 * and plural forms the engine matched.
	 *
	 * A guess, deliberately: the reader's spelling is compared against the stored value rather than
	 * against the folded form the index holds, so a word the engine matched through folding is
	 * missed here. A miss costs the row its Subject block and nothing else.
	 *
	 * Null when the query holds no word to match on.
	 */
	public static function fromQuery( string $query ): ?self {
		$words = self::wordsOf( $query );

		if ( $words === [] ) {
			return null;
		}

		$alternatives = implode( '|', array_map(
			static fn ( string $word ): string => preg_quote( $word, '/' ),
			$words
		) );

		return new self( '/((?<![\p{L}\p{N}])(?:' . $alternatives . ')[\p{L}\p{N}]*)/ui' );
	}

	/**
	 * Words of fewer characters than this are left out of the guess: an article or a preposition
	 * starts too many words to say which value the engine matched on.
	 */
	private const int MIN_WORD_LENGTH = 3;

	/**
	 * The words a reader searched for, with the query syntax around them taken off: the quotes of a
	 * phrase, the keyword of an operator such as `intitle:`, and the excluded terms, which are a
	 * reason a page was *not* left out rather than a reason it was found.
	 *
	 * @return string[]
	 */
	private static function wordsOf( string $query ): array {
		$words = [];

		foreach ( self::split( '/\s+/u', str_replace( '"', ' ', $query ) ) as $term ) {
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
	 * The text split so that every second part is matched text: the parts at even indexes fall
	 * outside a match and those at odd indexes are matched. Text without a match is a single part,
	 * and text opening with one starts with an empty part.
	 *
	 * @return string[]
	 */
	public function splitOnMatches( string $text ): array {
		$parts = preg_split( $this->pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		return $parts === false ? [ $text ] : $parts;
	}

}
