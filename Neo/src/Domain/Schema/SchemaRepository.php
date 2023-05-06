<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

interface SchemaRepository {

	public function getSchema( SchemaId $schemaName ): ?Schema;

}
