<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

/**
 * The store could not be reached or failed to serve the query: a transport error (HTTP status 0) or a
 * 5xx response. The store's raw detail is intentionally not surfaced to the user.
 */
class SparqlStoreUnavailableException extends SparqlQueryException {

	public function errorType(): string {
		return 'sparqlStoreUnavailable';
	}

}
