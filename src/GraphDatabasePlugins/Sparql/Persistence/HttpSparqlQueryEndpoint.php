<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence;

use MediaWiki\Http\HttpRequestFactory;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryEndpoint;

/**
 * Runs a SPARQL query over HTTP as a SPARQL 1.1 Protocol *query* operation (§ 2.1.3): the query text is
 * the POST body with a `application/sparql-query` content type, and `application/sparql-results+json` is
 * requested via the Accept header. A configured access token is sent as an HTTP Bearer token, matching
 * QLever's access-token scheme (QLever requires the token only for updates, but sending it on reads too
 * is harmless there and necessary for stores that also protect reads).
 *
 * Read-only by protocol, not by a validator. Unlike Cypher — where a single language expresses both
 * reads and writes, so a read-only validator is required — this class only ever issues a *query*
 * operation, and the SPARQL Query grammar (SELECT / ASK / CONSTRUCT / DESCRIBE) contains no update
 * forms. It targets the store's `queryUrl` and never posts an update; for QLever `queryUrl` and
 * `updateUrl` are the same value, so the real invariant is "only ever a query operation, never an
 * update", not a distinct URL.
 *
 * No connection is attempted at construction, so building the plugin stays I/O-free. Any non-2xx
 * response or transport failure (HTTP status 0) throws {@see SparqlQueryFailedException}.
 */
readonly class HttpSparqlQueryEndpoint implements SparqlQueryEndpoint {

	private const string CONTENT_TYPE = 'application/sparql-query';
	private const string ACCEPT = 'application/sparql-results+json';

	public function __construct(
		private HttpRequestFactory $httpRequestFactory,
		private string $queryUrl,
		private ?string $accessToken,
	) {
	}

	public function runQuery( string $sparql, int $timeoutSeconds ): string {
		$request = $this->httpRequestFactory->create(
			$this->queryUrl,
			[ 'method' => 'POST', 'postData' => $sparql, 'timeout' => $timeoutSeconds ],
			__METHOD__
		);

		$request->setHeader( 'Content-Type', self::CONTENT_TYPE );
		$request->setHeader( 'Accept', self::ACCEPT );

		if ( $this->accessToken !== null ) {
			$request->setHeader( 'Authorization', 'Bearer ' . $this->accessToken );
		}

		$request->execute();

		$status = $request->getStatus();
		if ( $status < 200 || $status >= 300 ) {
			throw new SparqlQueryFailedException( $this->queryUrl, $status, $request->getContent() );
		}

		return $request->getContent();
	}

}
