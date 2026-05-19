<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Databags\SummarizedResult;

interface Neo4jWriteQueryEngine {

	public function runWriteQuery( string $cypher ): SummarizedResult;

}
