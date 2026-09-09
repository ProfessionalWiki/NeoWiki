<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception;

class QueryPermissionDeniedException extends QueryException {

	public function errorType(): string {
		return 'permissionDenied';
	}

}
