<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\Neo;

class CypherQueryFilter {

	private const array WRITE_KEYWORDS = [
		'CREATE', 'SET', 'DELETE', 'REMOVE', 'MERGE', 'DROP'
	];

	public function isReadQuery( string $query ): bool {
		$normalizedQuery = $this->normalizeQuery( $query );

		if ( $this->containsWriteOperations( $normalizedQuery ) ) {
			return false;
		}

		if ( $this->containsFunctionCalls( $normalizedQuery ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize the query by removing comments and extra whitespace
	 *
	 * @param string $query The query to normalize
	 * @return string The normalized query
	 */
	private function normalizeQuery( string $query ): string {
		// Remove inline comments
		$query = preg_replace( '/\/\/.*$/m', '', $query );

		// Remove multi-line comments
		$query = preg_replace( '/\/\*.*?\*\//s', '', $query );

		// Convert to uppercase for easier keyword matching
		return strtoupper( $query );
	}

	/**
	 * Check if the query contains any write operations
	 *
	 * @param string $query The normalized query to check
	 * @return bool True if write operations are found, false otherwise
	 */
	private function containsWriteOperations( string $query ): bool {
		// Remove string literals to avoid false positives
		$queryWithoutStrings = preg_replace( '/([\'"])((?:\\\\\1|.)*?)\1/', '', $query );

		foreach ( self::WRITE_KEYWORDS as $keyword ) {
			if ( preg_match( '/\b' . preg_quote( $keyword, '/' ) . '\b/', $queryWithoutStrings ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if the query contains any function calls
	 *
	 * @param string $query The normalized query to check
	 * @return bool True if function calls are found, false otherwise
	 */
	private function containsFunctionCalls( string $query ): bool {
		// Remove string literals to avoid false positives
		$queryWithoutStrings = preg_replace( '/([\'"])((?:\\\\\1|.)*?)\1/', '', $query );

		// List of common Cypher keywords that might be followed by parentheses but are not functions
		$nonFunctionKeywords = [
			'MATCH', 'WHERE', 'RETURN', 'WITH', 'UNWIND', 'CASE', 'WHEN', 'THEN', 'ELSE',
			'AND', 'OR', 'XOR', 'NOT'
		];

		// Pattern to match function calls, excluding the common Cypher keywords
		$pattern = '/\b(?!(' . implode( '|', $nonFunctionKeywords ) . ')\b)[A-Z][A-Z0-9_]*\s*\(/';

		return preg_match( $pattern, $queryWithoutStrings ) === 1;
	}

}
