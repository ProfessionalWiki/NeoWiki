<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphStoreName;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Neo4jPlugin;

readonly class NeoWikiConfig {

	/**
	 * @param SparqlStoreConfig[] $sparqlStores The configured SPARQL graph stores (#586), possibly empty.
	 */
	public function __construct(
		public bool $enableDevelopmentUIs,
		public ?string $neo4jInternalWriteUrl,
		public ?string $neo4jInternalReadUrl,
		public string $wikiId,
		public string $rdfBaseUri,
		public array $sparqlStores,
	) {
	}

	public function hasNeo4jBackend(): bool {
		return self::neo4jConfigured( $this->neo4jInternalReadUrl, $this->neo4jInternalWriteUrl );
	}

	public static function neo4jConfigured( ?string $readUrl, ?string $writeUrl ): bool {
		return $readUrl !== null && $writeUrl !== null;
	}

	/**
	 * One of the two Neo4j Bolt URLs set and the other not, which is no backend rather than half of one.
	 */
	public function hasHalfConfiguredNeo4j(): bool {
		return !$this->hasNeo4jBackend()
			&& ( $this->neo4jInternalReadUrl !== null || $this->neo4jInternalWriteUrl !== null );
	}

	/**
	 * Whether the store the SPARQL read surfaces query — the first configured one, see
	 * {@see NeoWikiExtension::getFirstSparqlPlugin()} — holds the given projection, either as its own or
	 * through a sibling entry projecting into the same store. Sibling projections share a store, each in
	 * its own family of per-page named graphs (#1053), so a query against it sees them all.
	 *
	 * Entries are paired on `updateUrl`, the endpoint each projection is written to, since that is what
	 * decides which store holds it: a sibling writing elsewhere lands in another store and is not
	 * readable here. A store that reads and writes through different endpoints ({@see
	 * SparqlStoreConfig::$queryUrl}) is still one store, so it still pairs.
	 *
	 * The projection name is a Mapping page title (or `native`), compared as configured.
	 */
	public function queriedStoreHoldsProjection( string $projection ): bool {
		$queriedStore = $this->sparqlStores[0] ?? null;

		if ( $queriedStore === null ) {
			return false;
		}

		foreach ( $this->sparqlStores as $store ) {
			if ( $store->projection === $projection && $store->updateUrl === $queriedStore->updateUrl ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether at least one usable SPARQL store is present in the raw `NeoWikiSparqlStores` config. Used
	 * at registration time (before the config is parsed into {@see SparqlStoreConfig} objects) to gate
	 * the SPARQL query surfaces. The acceptance rule mirrors {@see NeoWikiConfigFactory::buildSparqlStore}
	 * and the name rules applied after it, so the route is present exactly when a plugin will be built:
	 * a route registered over a config every entry of which was dropped answers a query with a 500 where
	 * it should not answer at all.
	 */
	public static function hasConfiguredSparqlStore( mixed $rawStores ): bool {
		if ( !is_array( $rawStores ) ) {
			return false;
		}

		foreach ( $rawStores as $entry ) {
			if ( !is_array( $entry ) || !is_string( $entry['updateUrl'] ?? null ) || trim( $entry['updateUrl'] ) === '' ) {
				continue;
			}

			// A duplicate name is not among the rules checked here: the first entry claiming a name keeps
			// it, so a later duplicate being dropped can never leave the stores empty.
			$name = self::rawStoreName( $entry );

			if ( GraphStoreName::isTooLong( $name )
				|| GraphStoreName::isReserved( $name, [ Neo4jPlugin::STORE_NAME => true ] ) ) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * The name an entry would be filed under, derived exactly as {@see NeoWikiConfigFactory::buildSparqlStore}
	 * derives it: an explicit "name", or the projection it holds, or the native projection.
	 *
	 * @param array<string, mixed> $entry
	 */
	private static function rawStoreName( array $entry ): string {
		foreach ( [ 'name', 'projection' ] as $key ) {
			$value = $entry[$key] ?? null;

			if ( is_string( $value ) && trim( $value ) !== '' ) {
				return trim( $value );
			}
		}

		return RdfPageProjector::PROJECTION;
	}

}
