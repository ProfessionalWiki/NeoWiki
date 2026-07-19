<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;

/**
 * Sends a SPARQL 1.1 Query operation to a store's query endpoint and returns the raw response body (a
 * `application/sparql-results+json` document). The read-side sibling of {@see SparqlUpdateEndpoint}.
 *
 * @see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint for why
 *      no read-only validator is needed (a query operation cannot express an update).
 */
interface SparqlQueryEndpoint {

	/**
	 * @throws SparqlQueryFailedException on a non-2xx response or a transport error.
	 */
	public function runQuery( string $sparql, int $timeoutSeconds ): string;

}
