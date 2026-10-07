<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\Lua;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryPermissionDeniedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;

class CypherQueryRunner {

	public function __construct(
		private readonly Neo4jQueryService $queryService,
		private readonly Neo4jQueryLimits $limits,
	) {
	}

	public function run( string $cypher, array $params ): array {
		try {
			$result = $this->queryService->execute(
				new Neo4jQueryRequest(
					cypher: $cypher,
					parameters: $params,
					limits: $this->limits,
				)
			);
		} catch ( QueryPermissionDeniedException ) {
			// A LuaError would file the page under Scribunto's script-error category, for a wiki
			// configuration no module can fix.
			return [];
		}

		// Lua expects 1-indexed tables; Neo4jQueryResult::$rows is a 0-indexed list.
		$indexed = [];
		foreach ( $result->rows as $i => $row ) {
			$indexed[$i + 1] = $row;
		}
		return $indexed;
	}

}
