<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

class Schema {

	public function __construct(
		public readonly SchemaId $id,
		public readonly string $description,
		public readonly PropertyDefinitions $properties,
	) {
	}

}
