<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Domain\Schema\LabelTemplate;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

class TestSchema {

	public static function build(
		string|SchemaName $name = 'TestSchemaName',
		string $description = 'Test Schema Description',
		PropertyDefinitions $properties = new PropertyDefinitions( [] ),
		?string $labelTemplate = null,
	): Schema {
		return new Schema(
			name: $name instanceof SchemaName ? $name : new SchemaName( $name ),
			description: $description,
			properties: $properties,
			labelTemplate: $labelTemplate === null ? null : new LabelTemplate( $labelTemplate ),
		);
	}

	public static function reference( SchemaName|SchemaReference|string $schema ): SchemaReference {
		if ( $schema instanceof SchemaReference ) {
			return $schema;
		}

		return SchemaReference::local( $schema instanceof SchemaName ? $schema : new SchemaName( $schema ) );
	}

}
