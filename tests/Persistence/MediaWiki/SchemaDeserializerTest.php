<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaDeserializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaDeserializer
 *
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\ArrayProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty
 */
class SchemaDeserializerTest extends TestCase {

	public function testThrowsExceptionWhenSchemaIsInvalid(): void {
		$this->expectError();
		$this->expectErrorMessage( 'Undefined array key "type"' );

		$this->deserialize(
			<<<JSON
{
	"description": "Where are those TPS reports?",
	"propertyDefinitions": {
		"Is bankrupt": {
		}
	}
}
JSON
		);
	}

	private function deserialize( string $json ): Schema {
		return ( new SchemaDeserializer() )->deserialize(
			new SchemaName( 'SchemaDeserializerTest' ),
			$json
		);
	}

	public function testThrowsExceptionWhenTypeAndFormatMismatch(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'BooleanProperty must have a boolean format' );

		$this->deserialize(
			<<<JSON
{
	"description": "Where are those TPS reports?",
	"propertyDefinitions": {
		"Is bankrupt": {
			"type": "boolean",
			"format": "currency"
		}
	}
}
JSON
		);
	}

	public function testDeserializeSchemaWithAllTypes(): void {
		$schema = $this->deserialize(
			<<<JSON
{
	"title": "SchemaRepositoryTest_Valid",
	"description": "Where are those TPS reports?",
	"propertyDefinitions": {
		"Operating revenue": {
			"type": "number",
			"format": "currency",
			"minimum": 0,
			"maximum": 1337,
			"required": true,
			"default": 42
		},
		"Websites": {
			"type": "string",
			"format": "url",
			"description": "Websites owned by the company",
			"multiple": true
		},
		"Has product": {
			"type": "relation",
			"format": "relation",
			"label": "Product",
			"targetSchema": "Product",
			"multiple": true
		},
		"Is bankrupt": {
			"type": "boolean",
			"format": "checkbox"
		}
	}
}
JSON
		);

		$this->assertSame( 'SchemaDeserializerTest', $schema->getName()->getText() );
		$this->assertSame( 'Where are those TPS reports?', $schema->getDescription() );

		$this->assertEquals(
			new NumberProperty(
				format: ValueFormat::Currency,
				description: '',
				required: true,
				default: 42,
				minimum: 0,
				maximum: 1337
			),
			$schema->getProperty( 'Operating revenue' )
		);

		$this->assertEquals(
			new StringProperty(
				format: ValueFormat::Url,
				description: 'Websites owned by the company',
				required: false,
				default: '',
				multiple: true
			),
			$schema->getProperty( 'Websites' )
		);

		$this->assertEquals(
			new RelationProperty(
				description: '',
				required: false,
				default: null,
				relationType: new RelationType( 'Has product' ),
				targetSchema: new SchemaName( 'Product' ),
				multiple: true,
			),
			$schema->getProperty( 'Has product' )
		);

		$this->assertEquals(
			new BooleanProperty(
				format: ValueFormat::Checkbox,
				description: '',
				required: false,
				default: null,
			),
			$schema->getProperty( 'Is bankrupt' )
		);
	}

	public function testDeserializeMinimalNumberProperty(): void {
		$schema = $this->deserialize(
			<<<JSON
{
	"title": "SchemaRepositoryTest_Valid",
	"description": "Where are those TPS reports?",
	"propertyDefinitions": {
		"Operating revenue": {
			"type": "number",
			"format": "currency"
		}
	}
}
JSON
		);

		$this->assertEquals(
			new NumberProperty(
				format: ValueFormat::Currency,
				description: '',
				required: false,
				default: null,
				minimum: null,
				maximum: null
			),
			$schema->getProperty( 'Operating revenue' )
		);
	}

	public function testThrowsExceptionWhenJsonIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( 'invalid json' );
	}

}
