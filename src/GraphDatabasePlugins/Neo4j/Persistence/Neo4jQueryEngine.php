<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Databags\SummarizedResult;

interface Neo4jQueryEngine {

	public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult;

}
