<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application;

interface CypherQueryValidator {

	public function queryIsAllowed( string $cypher ): bool;

}
