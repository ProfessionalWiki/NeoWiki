<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception;

class BackendUnavailableException extends QueryException {

	public function errorType(): string {
		return 'backendUnavailable';
	}

}
