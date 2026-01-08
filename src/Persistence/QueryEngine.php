<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use Laudis\Neo4j\Databags\SummarizedResult;

interface QueryEngine {

	public function runReadQuery( string $cypher ): SummarizedResult;

}
