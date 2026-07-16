<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

use RuntimeException;

/**
 * Thrown by the HTTP query endpoint when a SPARQL query request fails: a non-2xx HTTP response, or a
 * transport error (which surfaces as HTTP status 0). Carries the HTTP status and a bounded snippet of
 * the response body so {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService}
 * can classify it (4xx client error vs 5xx/transport unavailability) and, for a client error, relay the
 * store's own detail to the author.
 *
 * The sibling of {@see SparqlUpdateFailedException} on the read side; kept separate because the query
 * service needs the status and snippet as data, not only baked into a message.
 */
class SparqlQueryFailedException extends RuntimeException {

	private const int MAX_BODY_SNIPPET_LENGTH = 500;

	public function __construct(
		public readonly string $endpointUrl,
		public readonly int $httpStatus,
		public readonly string $responseBody,
	) {
		parent::__construct(
			'The SPARQL query to <' . $endpointUrl . '> failed with HTTP status ' . $httpStatus
			. '. Response body: ' . $this->bodySnippet()
		);
	}

	public function bodySnippet(): string {
		$trimmed = trim( $this->responseBody );

		if ( mb_strlen( $trimmed ) <= self::MAX_BODY_SNIPPET_LENGTH ) {
			return $trimmed;
		}

		return mb_substr( $trimmed, 0, self::MAX_BODY_SNIPPET_LENGTH ) . '…';
	}

}
