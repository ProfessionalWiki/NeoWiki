<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\WikiPageSchemaLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaDeserializer
 *
 * @group database
 */
class WikiPageSchemaLookupTest extends NeoWikiIntegrationTestCase {

	public function testGetSchemaReturnsNullWhenPageDoesNotExists(): void {
		$this->assertNull(
			$this->newRepository()->getSchema( new SchemaName( 'SchemaRepositoryTest_404' ) )
		);
	}

	private function newRepository(): SchemaLookup {
		return NeoWikiExtension::getInstance()->getSchemaLookup();
	}

	public function testGetSchemaReturnsNullWhenPage(): void {
		$this->editPage( 'Schema:SchemaRepositoryTest_Wikitext', 'Hello world' );

		$this->assertNull(
			$this->newRepository()->getSchema( new SchemaName( 'SchemaRepositoryTest_Wikitext' ) )
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
			"type": "string",
			"format": "url",
			"description": "Websites owned by the company",
			"multiple": true
		}
	}
}
JSON
		);

		$schema = $this->newRepository()->getSchema( new SchemaName( 'SchemaRepositoryTest_Valid' ) );

		$this->assertSame( 'SchemaRepositoryTest_Valid', $schema->getName()->getText() );
		$this->assertSame( 'Where are those TPS reports?', $schema->getDescription() );

		$this->assertEquals(
			new StringProperty(
				format: ValueFormat::Url,
				description: 'Websites owned by the company',
				required: false,
				default: null,
				multiple: true
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
			$this->newRepository()->getSchema( new SchemaName( 'SchemaRepositoryTest_InvalidJson' ) )
		);
	}

}
