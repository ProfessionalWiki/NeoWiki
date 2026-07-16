<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryException;

/**
 * Maps a {@see SparqlQueryException} to the localized message that the wikitext-facing surfaces
 * ({{#sparql_raw}} and nw.sparqlQuery()) show to users. Returns the message key and its parameters
 * rather than rendered text, so each surface renders it in its own language context. Mirrors
 * {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\CypherErrorMessage}.
 */
readonly class SparqlErrorMessage {

	/**
	 * @param string[] $params
	 */
	public function __construct(
		public string $key,
		public array $params,
	) {
	}

	public static function for( SparqlQueryException $error ): self {
		return match ( $error->errorType() ) {
			'emptyQuery' => new self( 'neowiki-sparql-error-empty-query', [] ),
			'sparqlSyntaxError' => new self( 'neowiki-sparql-error-syntax', [ $error->getMessage() ] ),
			'sparqlStoreUnavailable' => new self( 'neowiki-sparql-error-store-unavailable', [] ),
			default => new self( 'neowiki-sparql-error-internal', [ $error->getMessage() ] ),
		};
	}

}
