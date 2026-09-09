<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception;

class SparqlQueryPermissionDeniedException extends SparqlQueryException {

	public function errorType(): string {
		return 'permissionDenied';
	}

}
