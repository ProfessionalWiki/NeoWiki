<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

interface SchemaLookup {

	public function getSchema( SchemaName $schemaId ): ?Schema;

}
