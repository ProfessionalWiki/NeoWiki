<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

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
	 * Whether the store the SPARQL read surfaces query — the first configured one, see
	 * {@see NeoWikiExtension::getFirstSparqlPlugin()} — holds the given projection, either as its own or
	 * through a sibling entry projecting into the same endpoint. Sibling projections share a store, each
	 * in its own family of per-page named graphs (#1053), so a query against that endpoint sees them all.
	 *
	 * The projection name is a Mapping page title (or `native`) and is matched exactly. Entries are
	 * matched on their query endpoint: a sibling writing elsewhere lands in another store, and its
	 * triples are not readable here.
	 */
	public function queriedStoreHoldsProjection( string $projection ): bool {
		$queriedStore = $this->sparqlStores[0] ?? null;

		if ( $queriedStore === null ) {
			return false;
		}

		foreach ( $this->sparqlStores as $store ) {
			if ( $store->projection === $projection && $store->queryUrl === $queriedStore->queryUrl ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether at least one usable SPARQL store is present in the raw `NeoWikiSparqlStores` config. Used
	 * at registration time (before the config is parsed into {@see SparqlStoreConfig} objects) to gate
	 * the SPARQL query surfaces. The acceptance rule — an array entry with a non-empty-string
	 * `updateUrl` — mirrors {@see NeoWikiConfigFactory::buildSparqlStore}, so the route is present
	 * exactly when a plugin will be built.
	 */
	public static function hasConfiguredSparqlStore( mixed $rawStores ): bool {
		if ( !is_array( $rawStores ) ) {
			return false;
		}

		foreach ( $rawStores as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['updateUrl'] ?? null ) && trim( $entry['updateUrl'] ) !== '' ) {
				return true;
			}
		}

		return false;
	}

}
