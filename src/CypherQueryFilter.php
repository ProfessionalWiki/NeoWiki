<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

class CypherQueryFilter {

	private const array WRITE_KEYWORDS = [
		'CREATE', 'SET', 'DELETE', 'REMOVE', 'MERGE', 'DROP',
		'CALL', 'LOAD', 'FOREACH',
		'GRANT', 'DENY', 'REVOKE',
		'SHOW',
	];

	public function isReadQuery( string $query ): bool {
		$normalizedQuery = $this->normalizeQuery( $query );

		return !$this->containsWriteOperations( $normalizedQuery );
	}

	/**
	 * Normalize the query by removing comments and extra whitespace
	 *
	 * @param string $query The query to normalize
	 * @return string The normalized query
	 */
	private function normalizeQuery( string $query ): string {
		// Remove inline comments
		$query = preg_replace( '/\/\/.*$/m', '', $query ) ?? $query;

		// Remove multi-line comments
		$query = preg_replace( '/\/\*.*?\*\//s', '', $query ) ?? $query;

		// Normalize unicode escape sequences that could be used for obfuscation
		$query = preg_replace( '/\\\\u[0-9A-Fa-f]{4}/', ' ', $query ) ?? $query;

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

}
