<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphDatabase;

/**
 * What a graph store may be called, wherever the name comes from.
 *
 * A store name is how a scoped rebuild is addressed and what its run records are filed under, so the
 * same two rules have to hold for a name out of `NeoWikiSparqlStores` and for one an extension
 * registers. They were only applied to the first, and a plugin taking a name the records cannot hold
 * got a store whose runs it could never find again.
 */
class GraphStoreName {

	/**
	 * As many bytes as a rebuild can file its run records under. The run records' own column is this
	 * wide, and a name cut to fit no longer matches the one every lookup passes.
	 */
	public const int MAX_LENGTH = 255;

	public static function isTooLong( string $name ): bool {
		return strlen( $name ) > self::MAX_LENGTH;
	}

	/**
	 * Whatever its casing: a store called "Neo4j" reads as the bundled backend wherever a name is
	 * written or reported, whether or not the two collide as array keys.
	 *
	 * @param array<string, true> $reservedNames Keys are the names the bundled backends hold
	 */
	public static function isReserved( string $name, array $reservedNames ): bool {
		foreach ( array_keys( $reservedNames ) as $reserved ) {
			if ( strcasecmp( $name, (string)$reserved ) === 0 ) {
				return true;
			}
		}

		return false;
	}

}
