<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\REST;

use ProfessionalWiki\NeoWiki\NeoWikiConfig;

/**
 * The read-side counterpart to
 * {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\Neo4jRouteRegistration}:
 * contributes the SPARQL query route only when at least one SPARQL store is configured.
 */
class SparqlRouteRegistration {

	/**
	 * REST route files the SPARQL plugin contributes given the raw `NeoWikiSparqlStores` config.
	 *
	 * @return string[]
	 */
	public static function routeFiles( mixed $rawStores ): array {
		if ( !NeoWikiConfig::hasConfiguredSparqlStore( $rawStores ) ) {
			return [];
		}

		return [ __DIR__ . '/sparqlRoutes.json' ];
	}

}
