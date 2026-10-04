<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use InvalidArgumentException;

class Cypher {

	/**
	 * Throws for the empty name and for a name holding a backslash, which Neo4j does not read literally
	 * inside backticks.
	 */
	public static function escape( string $name ): string {
		if ( $name === '' || str_contains( $name, '\\' ) ) {
			throw new InvalidArgumentException();
		}

		if ( self::nameIsSafe( $name ) ) {
			return $name;
		}

		return sprintf(
			"`%s`",
			str_replace( '`', '``', $name )
		);
	}

	private static function nameIsSafe( string $name ): bool {
		return (bool)\preg_match( '/^\p{L}[\p{L}\d_]*$/u', $name );
	}

}
