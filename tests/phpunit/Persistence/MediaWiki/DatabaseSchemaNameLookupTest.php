<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use MediaWiki\Title\TitleValue;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup
 * @group Database
 */
class DatabaseSchemaNameLookupTest extends NeoWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	private const array SETUP_SCHEMAS = [
		'SchemaNameLookupTest1',
		'SchemaNameLookupTest21',
		'SchemaNameLookupTest22',
		'SchemaNameLookupTest3',
	];

	public function setUp(): void {
		$this->truncateTables( [ 'page' ], $this->db );

		foreach ( self::SETUP_SCHEMAS as $name ) {
			$this->createSchema( $name );
		}
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

	public function testGetReadableSchemaNamesYieldsEverySchemaKeyedByNameInNameOrder(): void {
		$this->createSchema( 'Zebra' );
		$this->createSchema( 'Aardvark' );

		$this->assertSame(
			[
				'Aardvark' => 'Aardvark',
				'SchemaNameLookupTest1' => 'SchemaNameLookupTest1',
				'SchemaNameLookupTest21' => 'SchemaNameLookupTest21',
				'SchemaNameLookupTest22' => 'SchemaNameLookupTest22',
				'SchemaNameLookupTest3' => 'SchemaNameLookupTest3',
				'Zebra' => 'Zebra',
			],
			$this->readableNames( '', '' )
		);
	}

	public function testGetReadableSchemaNamesStartsAfterTheGivenName(): void {
		$this->assertSame(
			[
				'SchemaNameLookupTest22' => 'SchemaNameLookupTest22',
				'SchemaNameLookupTest3' => 'SchemaNameLookupTest3',
			],
			$this->readableNames( '', 'SchemaNameLookupTest21' )
		);
	}

	public function testGetReadableSchemaNamesYieldsOnlyNamesContainingTheSearch(): void {
		$this->assertSame(
			[
				'SchemaNameLookupTest21' => 'SchemaNameLookupTest21',
				'SchemaNameLookupTest22' => 'SchemaNameLookupTest22',
			],
			$this->readableNames( 'LookupTest2', '' )
		);
	}

	public function testGetReadableSchemaNamesMatchesTheSearchInAnyCase(): void {
		$this->assertSame(
			[
				'SchemaNameLookupTest21' => 'SchemaNameLookupTest21',
				'SchemaNameLookupTest22' => 'SchemaNameLookupTest22',
			],
			$this->readableNames( 'nameLOOKUPtest2', '' )
		);
	}

	public function testGetReadableSchemaNamesMatchesSpacesInTheSearch(): void {
		$this->createSchema( 'Computer' );
		$this->createSchema( 'Computer model' );

		$this->assertSame( [ 'Computer_model' => 'Computer model' ], $this->readableNames( 'r m', '' ) );
	}

	public function testGetReadableSchemaNamesReadsAnUnderscoreInTheSearchAsASpace(): void {
		$this->createSchema( 'Computer model' );

		$this->assertSame( [ 'Computer_model' => 'Computer model' ], $this->readableNames( 'r_m', '' ) );
	}

	public function testGetReadableSchemaNamesMatchesAnUnderscoreInTheSearchOnlyAsASpace(): void {
		$this->assertSame( [], $this->readableNames( 'LookupTes_2', '' ) );
	}

	public function testGetReadableSchemaNamesMatchesNothingForASearchNoNameContains(): void {
		$this->assertSame( [], $this->readableNames( 'Test2#', '' ) );
	}

	public function testGetReadableSchemaNamesTreatsAWhitespaceSearchAsNone(): void {
		$this->assertCount( 4, $this->readableNames( '  ', '' ) );
	}

	public function testGetReadableSchemaNamesContinuesASearchAfterTheGivenName(): void {
		$this->assertSame(
			[ 'SchemaNameLookupTest22' => 'SchemaNameLookupTest22' ],
			$this->readableNames( 'SchemaNameLookupTest2', 'SchemaNameLookupTest21' )
		);
	}

	public function testGetReadableSchemaNamesOmitsUnreadableSchemas(): void {
		// A denied Schema must not be yielded at all: the summaries endpoint fills its page from this
		// iterable and builds its cursor from the yielded keys, so a skipped Schema neither takes
		// page space nor becomes inferable from the pagination (#1062).
		$this->createSchema( 'GateHiddenSchema' );
		$this->createSchema( 'GateVisibleSchema' );

		$denyHidden = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'GateHiddenSchema';

		$this->assertSame(
			[
				'GateVisibleSchema' => 'GateVisibleSchema',
				'SchemaNameLookupTest1' => 'SchemaNameLookupTest1',
				'SchemaNameLookupTest21' => 'SchemaNameLookupTest21',
				'SchemaNameLookupTest22' => 'SchemaNameLookupTest22',
				'SchemaNameLookupTest3' => 'SchemaNameLookupTest3',
			],
			$this->readableNames( '', '', $this->mockRegisteredAuthority( $denyHidden ) )
		);
	}

	public function testGetReadableSchemaNamesDrainsEveryBatchInNameOrder(): void {
		// The generator pages the namespace in fixed-size keyset batches. With more rows than one batch,
		// it must keep querying past the first batch and yield every Schema exactly once, in name
		// order — a single truncated batch would drop the tail.
		$bulk = $this->createBarePages(
			NeoWikiExtension::NS_SCHEMA,
			'BulkSchema',
			DatabaseSchemaNameLookup::READABLE_NAMES_BATCH_SIZE + 20
		);

		$this->assertSame( $this->expectedByName( $bulk ), $this->readableNames( '', '' ) );
	}

	public function testGetReadableSchemaNamesFindsAMatchPastBatchesWithoutOne(): void {
		$this->createBarePages(
			NeoWikiExtension::NS_SCHEMA,
			'BulkSchema',
			DatabaseSchemaNameLookup::READABLE_NAMES_BATCH_SIZE + 20
		);
		$this->createSchema( 'Zebra' );

		$this->assertSame( [ 'Zebra' => 'Zebra' ], $this->readableNames( 'ebr', '' ) );
	}

	public function testGetReadableSchemaNamesContinuesPastAnUnreadableRowAtABatchBoundary(): void {
		// The Schema that sits exactly on the first batch boundary (the last row of the first full
		// batch) is denied. The drain's continue decision must count fetched rows, not yielded ones:
		// batch one comes back full yet yields one short, and the next batch must still be fetched, so
		// the rows past the boundary arrive and the denied row is the only one absent.
		$bulk = $this->createBarePages(
			NeoWikiExtension::NS_SCHEMA,
			'BulkSchema',
			DatabaseSchemaNameLookup::READABLE_NAMES_BATCH_SIZE + 20
		);

		$expected = $this->expectedByName( $bulk );
		$boundaryName = array_keys( $expected )[DatabaseSchemaNameLookup::READABLE_NAMES_BATCH_SIZE - 1];
		unset( $expected[$boundaryName] );

		$denyBoundary = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== $boundaryName;

		$this->assertSame(
			$expected,
			$this->readableNames( '', '', $this->mockRegisteredAuthority( $denyBoundary ) )
		);
	}

	/**
	 * @return array<string, string> Each yielded key with the name of its Schema.
	 */
	private function readableNames( string $search, string $afterName, ?Authority $authority = null ): array {
		return array_map(
			static fn ( TitleValue $title ): string => $title->getText(),
			iterator_to_array( $this->getLookup( $authority )->getReadableSchemaNames( $search, $afterName ) )
		);
	}

	/**
	 * The setUp Schemas and the bulk rows, keyed by name, in name order: what getReadableSchemaNames
	 * should yield when everything is readable.
	 *
	 * @param array<string, int> $bulk
	 * @return array<string, string>
	 */
	private function expectedByName( array $bulk ): array {
		$names = [ ...self::SETUP_SCHEMAS, ...array_keys( $bulk ) ];
		sort( $names, SORT_STRING );

		return array_combine( $names, $names );
	}

	private function getLookup( ?Authority $authority = null ): DatabaseSchemaNameLookup {
		return new DatabaseSchemaNameLookup(
			db: $this->getDb(),
			searchEngine: $this->getServiceContainer()->newSearchEngine(),
			readAuthorizer: new AuthorityBasedPageReadAuthorizer(
				$authority ?? $this->mockRegisteredUltimateAuthority(),
				$this->getServiceContainer()->getTitleFactory(),
				new NullLogger()
			),
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

	public function testUnreadableSchemaNamesAreOmittedFromSearchResults(): void {
		$denyFirstMatch = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'SchemaNameLookupTest21';

		$names = array_map(
			static fn ( TitleValue $title ): string => $title->getText(),
			$this->getLookup( $this->mockRegisteredAuthority( $denyFirstMatch ) )
				->getSchemaNamesMatching( 'SchemaNameLookupTest2', 10 )
		);

		$this->assertSame( [ 'SchemaNameLookupTest22' ], $names );
	}

}
