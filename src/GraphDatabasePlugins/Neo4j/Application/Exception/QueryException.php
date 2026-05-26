<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception;

use RuntimeException;

abstract class QueryException extends RuntimeException {

	abstract public function errorType(): string;

}
