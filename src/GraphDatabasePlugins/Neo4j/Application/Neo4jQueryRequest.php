<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application;

readonly class Neo4jQueryRequest {

	/**
	 * @param array<string,mixed> $parameters
	 */
	public function __construct(
		public string $cypher,
		public array $parameters,
		public Neo4jQueryLimits $limits,
	) {
	}

}
