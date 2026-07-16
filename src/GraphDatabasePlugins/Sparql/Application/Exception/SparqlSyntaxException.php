<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

/**
 * The store rejected the query with a 4xx status: a client problem, most commonly a SPARQL syntax
 * error. The message carries the store's own response detail so the author can fix the query.
 */
class SparqlSyntaxException extends SparqlQueryException {

	public function errorType(): string {
		return 'sparqlSyntaxError';
	}

}
