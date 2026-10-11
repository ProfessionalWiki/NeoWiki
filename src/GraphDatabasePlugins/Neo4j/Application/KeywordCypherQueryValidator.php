<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application;

class KeywordCypherQueryValidator implements CypherQueryValidator {

	private const array WRITE_KEYWORDS = [
		'CREATE', 'INSERT', 'SET', 'DELETE', 'REMOVE', 'MERGE', 'DROP',
		'CALL', 'LOAD', 'FOREACH',
		'GRANT', 'DENY', 'REVOKE',
		'SHOW',
	];

	/**
	 * Comments, string literals and quoted names, matched in one left-to-right pass, so that a quote or comment
	 * marker inside one of them cannot start or end another. Strings and names are matched possessively, a run
	 * of plain characters at a time, which keeps a long one within PCRE's stack.
	 */
	private const string COMMENT_OR_QUOTED_TEXT =
		'/\/\/[^\r\n]*|\/\*.*?\*\/|\'[^\'\\\\]*+(?:\\\\.[^\'\\\\]*+)*+\'|"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"|`[^`]*+(?:``[^`]*+)*+`/s';

	/**
	 * A backslash starts a unicode escape unless it is itself escaped: only after an even number of backslashes.
	 */
	private const string UNICODE_ESCAPE = '/(?<!\\\\)((?:\\\\\\\\)*+)\\\\u([0-9A-Fa-f]{4})/';

	public function queryIsAllowed( string $cypher ): bool {
		$decoded = $this->decodeUnicodeEscapes( $cypher );

		if ( $decoded === null ) {
			return false;
		}

		$code = $this->removeCommentsAndQuotedText( $decoded );

		return preg_match( '/\b(?:' . implode( '|', self::WRITE_KEYWORDS ) . ')\b/i', $code ) === 0;
	}

	/**
	 * Neo4j decodes unicode escapes before it tokenizes the query, so an escape can spell a keyword or close a
	 * string, a quoted name or a comment.
	 */
	private function decodeUnicodeEscapes( string $query ): ?string {
		return preg_replace_callback(
			self::UNICODE_ESCAPE,
			static fn ( array $match ): string => $match[1] . self::character( (int)hexdec( $match[2] ) ),
			$query
		);
	}

	/**
	 * A surrogate is no character on its own, but its escape must not vanish from the query either.
	 */
	private static function character( int $codePoint ): string {
		return $codePoint >= 0xD800 && $codePoint <= 0xDFFF ? "\u{FFFD}" : mb_chr( $codePoint, 'UTF-8' );
	}

	private function removeCommentsAndQuotedText( string $query ): string {
		return preg_replace( self::COMMENT_OR_QUOTED_TEXT, ' ', $query ) ?? $query;
	}

}
