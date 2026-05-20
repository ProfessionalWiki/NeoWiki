<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application;

readonly class Neo4jQueryResult {

	/**
	 * @param list<string> $columns
	 * @param list<array<string,mixed>> $rows
	 */
	public function __construct(
		public array $columns,
		public array $rows,
		public bool $truncated,
		public int $resultCount,
		public int $durationMs,
	) {
	}

}
