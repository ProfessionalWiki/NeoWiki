<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;

class TestSchema {

	public static function build(
		string|SchemaId $id = 'TestSchemaId',
		string $description = 'Test Schema Description',
		PropertyDefinitions $properties = new PropertyDefinitions( [] ),
	): Schema {
		return new Schema(
			id: $id instanceof SchemaId ? $id : new SchemaId( $id ),
			description: $description,
			properties: $properties,
		);
	}

}
