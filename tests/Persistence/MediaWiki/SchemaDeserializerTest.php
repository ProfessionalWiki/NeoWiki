<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\ArrayProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
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
			new SchemaId( 'SchemaDeserializerTest' ),
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
			"maximum": 1337
		},
		"Websites": {
			"type": "array",
			"description": "Websites owned by the company",
			"items": {
				"type": "string",
				"format": "url"
			}
		},
		"Has product": {
			"type": "array",
			"label": "Products",
			"items": {
				"type": "relation",
				"format": "relation",
				"label": "Product",
				"targetSchema": "Product"
			}
		},
		"Is bankrupt": {
			"type": "boolean",
			"format": "checkbox"
		}
	}
}
JSON
		);

		$this->assertSame( 'SchemaDeserializerTest', $schema->getId()->getText() );
		$this->assertSame( 'Where are those TPS reports?', $schema->getDescription() );

		$this->assertEquals(
			new NumberProperty( format: ValueFormat::Currency, description: '', minimum: 0, maximum: 1337 ),
			$schema->getProperty( 'Operating revenue' )
		);

		$this->assertEquals(
			new ArrayProperty(
				description: 'Websites owned by the company',
				itemDefinition: new StringProperty( format: ValueFormat::Url, description: '' )
			),
			$schema->getProperty( 'Websites' )
		);

		$this->assertEquals(
			new ArrayProperty(
				description: '',
				itemDefinition: new RelationProperty(
					description: '',
					targetSchema: new SchemaId( 'Product' )
				)
			),
			$schema->getProperty( 'Has product' )
		);

		$this->assertEquals(
			new BooleanProperty( format: ValueFormat::Checkbox, description: '' ),
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
			new NumberProperty( format: ValueFormat::Currency, description: '', minimum: null, maximum: null ),
			$schema->getProperty( 'Operating revenue' )
		);
	}

	public function testThrowsExceptionWhenJsonIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->deserialize( 'invalid json' );
	}

}
