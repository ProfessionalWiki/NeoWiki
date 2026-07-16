<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\EmptySparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\InternalSparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlStoreUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlSyntaxException;

/**
 * Runs a read-only SPARQL query against one store and returns the W3C results document. The read-side
 * counterpart to {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService}.
 *
 * Two deliberate differences from the Cypher service:
 *
 *  - No read-only validator. Cypher can express writes, so it needs one; the query is instead sent as a
 *    SPARQL 1.1 Protocol *query* operation, and the SPARQL Query grammar contains no update forms (see
 *    {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint}).
 *  - No normalizer and no envelope. Bolt records are not JSON, so the Cypher path assembles rows into an
 *    envelope; the SPARQL response already *is* the `application/sparql-results+json` document, returned
 *    unmodified. As a consequence the `maxRows` cap is not applied: truncating `results.bindings` would
 *    alter that document, and signaling the truncation would require inventing a field. Only the per-tier
 *    timeout is enforced (as the HTTP client timeout).
 */
readonly class SparqlQueryService {

	public function __construct(
		private SparqlQueryEndpoint $endpoint,
	) {
	}

	public function execute( SparqlQueryRequest $request ): SparqlQueryResult {
		$sparql = trim( $request->sparql );

		if ( $sparql === '' ) {
			throw new EmptySparqlQueryException( 'Query is empty.' );
		}

		try {
			$body = $this->endpoint->runQuery( $sparql, $request->limits->timeoutSeconds );
		} catch ( SparqlQueryFailedException $e ) {
			throw $this->translateFailure( $e );
		}

		$document = json_decode( $body, true );

		if ( !is_array( $document ) ) {
			throw new InternalSparqlQueryException(
				'The SPARQL store returned a response that is not a JSON results document.'
			);
		}

		return new SparqlQueryResult( $document );
	}

	private function translateFailure( SparqlQueryFailedException $failure ): SparqlQueryException {
		// A 4xx means the store rejected the request itself — for a query that is almost always a SPARQL
		// syntax error. Relay the store's own detail so the author can fix the query.
		if ( $failure->httpStatus >= 400 && $failure->httpStatus < 500 ) {
			return new SparqlSyntaxException( $failure->bodySnippet(), 0, $failure );
		}

		// A 5xx response or a transport error (status 0) means the store is unavailable.
		return new SparqlStoreUnavailableException( 'The SPARQL store is unavailable.', 0, $failure );
	}

}
