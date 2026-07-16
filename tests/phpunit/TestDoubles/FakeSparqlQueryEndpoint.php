<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryEndpoint;

/**
 * A SPARQL query endpoint that returns a canned response body (or throws a canned failure) without any
 * HTTP, and records the query and timeout it was called with. Lets tests drive the query service and the
 * surfaces above it deterministically.
 */
class FakeSparqlQueryEndpoint implements SparqlQueryEndpoint {

	public ?string $lastQuery = null;
	public ?int $lastTimeoutSeconds = null;

	private function __construct(
		private readonly ?string $responseBody,
		private readonly ?SparqlQueryFailedException $failure,
	) {
	}

	public static function returning( string $responseBody ): self {
		return new self( $responseBody, null );
	}

	public static function failingWith( SparqlQueryFailedException $failure ): self {
		return new self( null, $failure );
	}

	public function runQuery( string $sparql, int $timeoutSeconds ): string {
		$this->lastQuery = $sparql;
		$this->lastTimeoutSeconds = $timeoutSeconds;

		if ( $this->failure !== null ) {
			throw $this->failure;
		}

		return (string)$this->responseBody;
	}

}
