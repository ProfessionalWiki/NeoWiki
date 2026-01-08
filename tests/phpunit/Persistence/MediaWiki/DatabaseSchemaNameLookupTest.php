<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use TitleValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup
 * @group Database
 */
class DatabaseSchemaNameLookupTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->tablesUsed[] = 'page';
		$this->truncateTables( $this->tablesUsed, $this->db );

		$this->createSchema( 'SchemaNameLookupTest1' );
		$this->createSchema( 'SchemaNameLookupTest21' );
		$this->createSchema( 'SchemaNameLookupTest22' );
		$this->createSchema( 'SchemaNameLookupTest3' );
	}

	/**
	 * @dataProvider emptyInputProvider
	 */
	public function testReturnsSchemasOnEmptyInput( string $emptySearch ): void {
		$this->assertEquals(
			[
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest1' ),
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest21' ),
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest22' ),
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest3' ),
			],
			$this->getLookup()->getSchemaNamesMatching( $emptySearch )
		);
	}

	private function getLookup(): DatabaseSchemaNameLookup {
		return NeoWikiExtension::getInstance()->getSchemaNameLookup();
	}

	public static function emptyInputProvider(): array {
		return [
			[ '' ],
			[ ' ' ],
			[ '  ' ],
		];
	}

	public function testReturnsOnlySchemasMatchingTheSearch(): void {
		$this->assertEquals(
			[
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest21' ),
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest22' ),
			],
			$this->getLookup()->getSchemaNamesMatching( 'SchemaNameLookupTest2' )
		);
	}

	public function testReturnsEmptyArrayIfNothingMatchesTheSearch(): void {
		$this->assertSame(
			[],
			$this->getLookup()->getSchemaNamesMatching( 'SchemaNameLookupTest4' )
		);
	}

}
