<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer
 *
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\CheckboxProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\CurrencyProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty
 */
class SchemaPersistenceDeserializerTest extends TestCase {

	public function testThrowsExceptionWhenSchemaIsInvalid(): void {
		$this->expectError();
		$this->expectErrorMessage( 'Undefined array key "format"' );

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
		return ( new SchemaPersistenceDeserializer( NeoWikiExtension::getInstance()->getValueFormatLookup() ) )->deserialize(
			new SchemaName( 'SchemaDeserializerTest' ),
			$json
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
			"format": "currency",
			"currencyCode": "BTC",
			"minimum": 0,
			"maximum": 1337,
			"required": true,
			"default": 42
		},
		"Websites": {
			"format": "url",
			"description": "Websites owned by the company",
			"multiple": true
		},
		"Has product": {
			"format": "relation",
			"relation": "HasProduct",
			"targetSchema": "Product",
			"multiple": true
		},
		"Is bankrupt": {
			"format": "checkbox"
		}
	}
}
JSON
		);

		$this->assertSame( 'SchemaDeserializerTest', $schema->getName()->getText() );
		$this->assertSame( 'Where are those TPS reports?', $schema->getDescription() );

		$this->assertEquals(
			TestProperty::buildCurrency(
				required: true,
				default: 42,
				currencyCode: 'BTC',
				minimum: 0,
				maximum: 1337
			),
			$schema->getProperty( 'Operating revenue' )
		);

		$this->assertEquals(
			TestProperty::buildUrl(
				description: 'Websites owned by the company',
				required: false,
				default: '',
				multiple: true
			),
			$schema->getProperty( 'Websites' )
		);

		$this->assertEquals(
			TestProperty::buildRelation(
				description: '',
				required: false,
				default: null,
				relationType: new RelationType( 'HasProduct' ),
				targetSchema: new SchemaName( 'Product' ),
				multiple: true,
			),
			$schema->getProperty( 'Has product' )
		);

		$this->assertEquals(
			TestProperty::buildCheckbox(
				description: '',
				required: false,
				default: false,
			),
			$schema->getProperty( 'Is bankrupt' )
		);
	}

	public function testDeserializeMinimalCurrencyProperty(): void {
		$schema = $this->deserialize(
			<<<JSON
{
	"title": "SchemaRepositoryTest_Valid",
	"description": "Where are those TPS reports?",
	"propertyDefinitions": {
		"Operating revenue": {
			"format": "currency",
			"currencyCode": "BTC"
		}
	}
}
JSON
		);

		$this->assertEquals(
			TestProperty::buildCurrency(
				required: false,
				default: null,
				currencyCode: 'BTC',
				precision: null,
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
