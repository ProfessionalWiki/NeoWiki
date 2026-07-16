<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

/**
 * A failure not covered by the more specific cases: most notably a 2xx response whose body is not a
 * decodable JSON results document.
 */
class InternalSparqlQueryException extends SparqlQueryException {

	public function errorType(): string {
		return 'internalError';
	}

}
