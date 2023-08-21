<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

interface SchemaLookup {

	public function getSchema( SchemaName $schemaId ): ?Schema;

}
