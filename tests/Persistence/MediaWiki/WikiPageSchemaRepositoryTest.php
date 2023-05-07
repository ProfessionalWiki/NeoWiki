<?php

declare( strict_types = 1 );

namespace Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\ArrayProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
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
			"items": {
				"type": "string",
				"format": "url"
			}
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
				description: '',
				itemDefinition: new StringProperty( format: ValueFormat::Url, description: '' )
			),
			$schema->properties->getProperty( 'Websites' )
		);
	}

}
