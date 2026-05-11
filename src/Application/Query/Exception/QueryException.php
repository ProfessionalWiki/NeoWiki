<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Query\Exception;

use RuntimeException;

abstract class QueryException extends RuntimeException {

	abstract public function errorType(): string;

}
