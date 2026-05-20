<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CypherQueryValidator;

class SpyCypherQueryValidator implements CypherQueryValidator {

	public int $callCount = 0;

	public function queryIsAllowed( string $cypher ): bool {
		$this->callCount++;
		return true;
	}

}
