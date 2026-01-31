<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application;

interface CypherQueryValidator {

	public function queryIsAllowed( string $cypher ): bool;

}
