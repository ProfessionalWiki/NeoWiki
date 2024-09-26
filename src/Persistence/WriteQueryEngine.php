<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence;

use Laudis\Neo4j\Databags\SummarizedResult;

interface WriteQueryEngine {

	public function runWriteQuery( string $cypher ): SummarizedResult;

}
