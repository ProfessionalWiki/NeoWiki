<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j;

use InvalidArgumentException;

class Cypher {

	public static function escape( string $name ): string {
		if ( $name === '' ) {
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

	/**
	 * @param string[] $labels
	 */
	public static function buildLabelList( array $labels ): string {
		return implode(
			':',
			array_map(
				self::escape( ... ),
				$labels
			)
		);
	}

}
