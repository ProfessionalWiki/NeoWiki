<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

/**
 * The store's response to a query: the W3C `application/sparql-results+json` document, decoded and
 * otherwise unmodified. NeoWiki adds no envelope of its own around it (unlike the Cypher surface, whose
 * result is assembled from non-JSON Bolt records) — every surface renders this document directly.
 */
readonly class SparqlQueryResult {

	/**
	 * @param array<string, mixed> $document The decoded SPARQL results document (`head`, `results`, or
	 *   `boolean` for an ASK query), plus any store-specific extras (e.g. QLever's `meta`).
	 */
	public function __construct(
		public array $document,
	) {
	}

}
