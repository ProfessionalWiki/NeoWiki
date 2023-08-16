<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\RunCypher;

use ProfessionalWiki\NeoWiki\Application\QueryEngine;

class RunCypherQuery {

	public function __construct(
		private readonly RunCypherPresenter $presenter,
		private readonly QueryEngine $queryEngine,
	) {
	}

	public function runCypher( string $cypherQuery ): void {
		if ( trim( $cypherQuery ) === '' ) {
			$this->presenter->presentError( RunCypherError::NO_QUERY );
			return;
		}

		$result = $this->queryEngine->runReadQuery( $cypherQuery );

		$this->presenter->presentSummarizedResult( $result );
	}

}
