<?php

declare( strict_types = 1 );

namespace Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\ArrayProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaRepository;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaRepository
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaDeserializer
 *
 * @group database
 */
class WikiPageSchemaRepositoryTest extends NeoWikiIntegrationTestCase {

	public function testGetSchemaReturnsNullWhenPageDoesNotExists(): void {
		$this->assertNull(
			$this->newRepository()->getSchema( new SchemaId( 'SchemaRepositoryTest_404' ) )
		);
	}

	private function newRepository(): SchemaRepository {
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
		"Websites": {
			"type": "array",
			"description": "Websites owned by the company",
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

		$this->assertSame( 'SchemaRepositoryTest_Valid', $schema->getId()->getText() );
		$this->assertSame( 'Where are those TPS reports?', $schema->getDescription() );

		$this->assertEquals(
			new ArrayProperty(
				description: 'Websites owned by the company',
				itemDefinition: new StringProperty( format: ValueFormat::Url, description: '' )
			),
			$schema->getProperty( 'Websites' )
		);
	}

	public function testReturnsNullOnInvalidJson(): void {
		$this->createSchema(
			'SchemaRepositoryTest_InvalidJson',
			'~=[,,_,,]:3'
		);

		$this->assertNull(
			$this->newRepository()->getSchema( new SchemaId( 'SchemaRepositoryTest_InvalidJson' ) )
		);
	}

}
