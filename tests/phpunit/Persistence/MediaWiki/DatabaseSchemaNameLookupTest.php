<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use MediaWiki\Title\TitleValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup
 * @group Database
 */
class DatabaseSchemaNameLookupTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

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
			$this->getLookup()->getSchemaNamesMatching( $emptySearch, 10 )
		);
	}

	private function getLookup( ?Authority $authority = null ): DatabaseSchemaNameLookup {
		return new DatabaseSchemaNameLookup(
			db: $this->getDb(),
			searchEngine: $this->getServiceContainer()->newSearchEngine(),
			authority: $authority ?? $this->mockRegisteredUltimateAuthority(),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
		);
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
			$this->getLookup()->getSchemaNamesMatching( 'SchemaNameLookupTest2', 10 )
		);
	}

	public function testReturnsEmptyArrayIfNothingMatchesTheSearch(): void {
		$this->assertSame(
			[],
			$this->getLookup()->getSchemaNamesMatching( 'SchemaNameLookupTest4', 10 )
		);
	}

	public function testLimitRestrictsResults(): void {
		$this->assertEquals(
			[
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest1' ),
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest21' ),
			],
			$this->getLookup()->getSchemaNamesMatching( '', 2 )
		);
	}

	public function testOffsetSkipsResults(): void {
		$this->assertEquals(
			[
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest22' ),
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest3' ),
			],
			$this->getLookup()->getSchemaNamesMatching( '', 10, 2 )
		);
	}

	public function testLimitAndOffsetCombined(): void {
		$this->assertEquals(
			[
				new TitleValue( NeoWikiExtension::NS_SCHEMA, 'SchemaNameLookupTest21' ),
			],
			$this->getLookup()->getSchemaNamesMatching( '', 1, 1 )
		);
	}

	public function testGetSchemaCount(): void {
		$this->assertSame( 4, $this->getLookup()->getSchemaCount() );
	}

	public function testUnreadableSchemaNamesAreOmitted(): void {
		// GateHiddenSchema is created before GateVisibleSchema so the denied row sits mid-list
		// (not last), which is what makes the assertion below sensitive to a missing
		// array_values() reindex: dropping the array_values would leave a gap in the array
		// keys, and json_encode() (used by GetSchemaNamesApi) would serialize the result as a
		// JSON object instead of an array.
		$this->createSchema( 'GateHiddenSchema' );
		$this->createSchema( 'GateVisibleSchema' );

		$denyHidden = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'GateHiddenSchema';

		$names = array_map(
			static fn ( TitleValue $title ): string => $title->getText(),
			$this->getLookup( $this->mockRegisteredAuthority( $denyHidden ) )->getSchemaNamesMatching( '', 10 )
		);

		$this->assertSame(
			[
				'SchemaNameLookupTest1',
				'SchemaNameLookupTest21',
				'SchemaNameLookupTest22',
				'SchemaNameLookupTest3',
				'GateVisibleSchema',
			],
			$names
		);
	}

	public function testGateUsesBindingAuthorizeRead(): void {
		// probablyCan is a UI-hint check that skips the expensive ACL hook; the filter must
		// use the binding authorizeRead with the 'read' action. Reverting fails this test.
		$this->createSchema( 'GatePinSchema' );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'probablyCan' )->willReturn( true );
		$authority->method( 'authorizeRead' )->willReturnCallback(
			function ( string $action ): bool {
				$this->assertSame( 'read', $action );
				return false;
			}
		);

		$names = array_map(
			static fn ( TitleValue $title ): string => $title->getText(),
			$this->getLookup( $authority )->getSchemaNamesMatching( '', 50 )
		);

		$this->assertNotContains( 'GatePinSchema', $names );
		$this->assertSame( [], $names );
	}

}
