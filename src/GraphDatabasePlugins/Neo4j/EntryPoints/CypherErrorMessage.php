<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;

/**
 * Maps a QueryException to the localized message that wikitext-facing surfaces
 * ({{#cypher_raw}} and nw.query()) show to users. Returns the message key and its
 * parameters rather than rendered text, so each surface can render it in its own
 * language context.
 */
readonly class CypherErrorMessage {

	/**
	 * @param string[] $params
	 */
	public function __construct(
		public string $key,
		public array $params,
	) {
	}

	public static function for( QueryException $error ): self {
		return match ( $error->errorType() ) {
			'emptyQuery' => new self( 'neowiki-cypher-error-empty-query', [] ),
			'writeQueryRejected' => new self( 'neowiki-cypher-error-write-query', [] ),
			'backendUnavailable' => new self( 'neowiki-cypher-error-backend-unavailable', [] ),
			'cypherSyntaxError' => new self( 'neowiki-cypher-error-syntax', [ $error->getMessage() ] ),
			'parameterMissing' => new self( 'neowiki-cypher-error-parameter-missing', [ $error->getMessage() ] ),
			'queryTimeout' => new self( 'neowiki-cypher-error-timeout', [ $error->getMessage() ] ),
			default => new self( 'neowiki-cypher-error-internal', [ $error->getMessage() ] ),
		};
	}

}
