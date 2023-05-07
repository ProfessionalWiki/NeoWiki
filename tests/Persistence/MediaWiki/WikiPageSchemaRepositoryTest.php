<?php

declare( strict_types = 1 );

namespace Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\ArrayProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaRepository;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaRepository
 * @group database
 */
class WikiPageSchemaRepositoryTest extends NeoWikiIntegrationTestCase {

	public function testGetSchemaReturnsNullWhenPageDoesNotExists(): void {
		$this->assertNull(
			$this->newRepository()->getSchema( new SchemaId( 'SchemaRepositoryTest_404' ) )
		);
	}

	private function newRepository(): WikiPageSchemaRepository {
		return NeoWikiExtension::getInstance()->newSchemaRepository();
	}

	public function testGetSchemaReturnsNullWhenPage(): void {
		$this->editPage( 'Schema:SchemaRepositoryTest_Wikitext', 'Hello world' );

		$this->assertNull(
			$this->newRepository()->getSchema( new SchemaId( 'SchemaRepositoryTest_Wikitext' ) )
		);
	}

	public function testGetSchemaReturnsSchema(): void {
		$this->createSchema(
			'SchemaRepositoryTest_Valid',
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

		$schema = $this->newRepository()->getSchema( new SchemaId( 'SchemaRepositoryTest_Valid' ) );

		$this->assertSame( 'SchemaRepositoryTest_Valid', $schema->id->getText() );
		$this->assertSame( 'Where are those TPS reports?', $schema->description );

		$this->assertEquals(
			new NumberProperty( format: ValueFormat::Currency, description: '', minimum: 0, maximum: 1337 ),
			$schema->properties->getProperty( 'Operating revenue' )
		);

		$this->assertEquals(
			new ArrayProperty(
				description: 'Websites owned by the company',
				itemDefinition: new StringProperty( format: ValueFormat::Url, description: '' )
			),
			$schema->properties->getProperty( 'Websites' )
		);

		$this->assertEquals(
			new ArrayProperty(
				description: '',
				itemDefinition: new RelationProperty(
					description: '',
					targetSchema: new SchemaId( 'Product' )
				)
			),
			$schema->properties->getProperty( 'Has product' )
		);

		$this->assertEquals(
			new BooleanProperty( format: ValueFormat::Checkbox, description: '' ),
			$schema->properties->getProperty( 'Is bankrupt' )
		);
	}

	public function testThrowsExceptionWhenSchemaIsInvalid(): void {
		// TODO: maybe we should catch the error and return null instead?

		$this->createSchema(
			'SchemaRepositoryTest_MissingType',
			<<<JSON
{
	"title": "SchemaRepositoryTest_MissingType",
	"description": "Where are those TPS reports?",
	"propertyDefinitions": {
		"Is bankrupt": {
		}
	}
}
JSON

		);

		$this->expectError();
		$this->expectErrorMessage( 'Undefined array key "type"' );

		$this->newRepository()->getSchema( new SchemaId( 'SchemaRepositoryTest_MissingType' ) );
	}

}
