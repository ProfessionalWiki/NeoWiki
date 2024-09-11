<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\RunCypher;

use Laudis\Neo4j\Exception\Neo4jException;
use ProfessionalWiki\NeoWiki\Application\QueryEngine;

readonly class RunCypherQuery {

	public function __construct(
		private RunCypherPresenter $presenter,
		private QueryEngine $queryEngine,
	) {
	}

	public function runCypher( string $cypherQuery ): void {
		if ( trim( $cypherQuery ) === '' ) {
			$this->presenter->presentError( RunCypherError::NO_QUERY );
			return;
		}

		try {
			$result = $this->queryEngine->runReadQuery( $cypherQuery );
			$this->presenter->presentSummarizedResult( $result );
		} catch ( Neo4jException $e ) {
			// TODO: check if this is the right failure - https://neo4j.com/docs/status-codes/5/errors/all-errors/
			$this->presenter->presentError( RunCypherError::NOT_A_READ_QUERY );
		}
	}

}
