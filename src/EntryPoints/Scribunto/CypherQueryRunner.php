<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Scribunto;

use MediaWiki\Context\RequestContext;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryException;
use ProfessionalWiki\NeoWiki\Application\Query\QueryLimits;
use ProfessionalWiki\NeoWiki\Application\Query\Cypher\QueryRequest;
use ProfessionalWiki\NeoWiki\Application\Query\Cypher\QueryService;
use RuntimeException;

class CypherQueryRunner {

	public function __construct(
		private readonly QueryService $queryService,
	) {
	}

	public function run( string $cypher, array $params ): array {
		try {
			$result = $this->queryService->execute(
				new QueryRequest(
					cypher: $cypher,
					parameters: $params,
					limits: QueryLimits::forUser( RequestContext::getMain()->getUser() ),
				)
			);
		} catch ( QueryException $e ) {
			throw new RuntimeException( $e->getMessage(), 0, $e );
		}

		// Lua expects 1-indexed tables; QueryResult::$rows is a 0-indexed list.
		$indexed = [];
		foreach ( $result->rows as $i => $row ) {
			$indexed[$i + 1] = $row;
		}
		return $indexed;
	}

}
