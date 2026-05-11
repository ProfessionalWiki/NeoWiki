<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Scribunto;

use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Application\Query\QueryResultNormalizer;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\QueryEngine;
use RuntimeException;

class CypherQueryRunner {

	public function __construct(
		private readonly QueryEngine $queryEngine,
		private readonly CypherQueryValidator $validator,
		private readonly QueryResultNormalizer $converter,
	) {
	}

	public function run( string $cypher, array $params ): array {
		$cypher = trim( $cypher );

		if ( $cypher === '' ) {
			throw new RuntimeException(
				wfMessage( 'neowiki-lua-query-error-empty-query' )->text()
			);
		}

		if ( !$this->validator->queryIsAllowed( $cypher ) ) {
			throw new RuntimeException(
				wfMessage( 'neowiki-lua-query-error-write-query' )->text()
			);
		}

		return $this->converter->convertRows(
			$this->queryEngine->runReadQuery( $cypher, $params )
		);
	}

}
