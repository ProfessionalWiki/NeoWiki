<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

use RuntimeException;

/**
 * Thrown when a SPARQL Update request fails: a non-2xx HTTP response, or a transport error (which
 * surfaces as HTTP status 0). Carries a bounded snippet of the response body to aid diagnosis without
 * flooding the logs.
 */
class SparqlUpdateFailedException extends RuntimeException {

	private const int MAX_BODY_SNIPPET_LENGTH = 500;

	public function __construct( string $endpointUrl, int $httpStatus, string $responseBody ) {
		parent::__construct(
			'The SPARQL update to <' . $endpointUrl . '> failed with HTTP status ' . $httpStatus
			. '. Response body: ' . self::snippet( $responseBody )
		);
	}

	private static function snippet( string $body ): string {
		$trimmed = trim( $body );

		if ( mb_strlen( $trimmed ) <= self::MAX_BODY_SNIPPET_LENGTH ) {
			return $trimmed;
		}

		return mb_substr( $trimmed, 0, self::MAX_BODY_SNIPPET_LENGTH ) . '…';
	}

}
