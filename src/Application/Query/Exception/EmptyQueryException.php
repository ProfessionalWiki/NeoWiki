<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Query\Exception;

class EmptyQueryException extends QueryException {

	public function errorType(): string {
		return 'emptyQuery';
	}

}
