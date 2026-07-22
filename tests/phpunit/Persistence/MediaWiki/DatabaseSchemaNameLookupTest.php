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

	/**
	 * @var array<string, int>
	 */
	private array $pageIds = [];

	public function setUp(): void {
		$this->tablesUsed[] = 'page';
		$this->truncateTables( $this->tablesUsed, $this->db );

		foreach ( [ 'SchemaNameLookupTest1', 'SchemaNameLookupTest21', 'SchemaNameLookupTest22', 'SchemaNameLookupTest3' ] as $name ) {
			$this->pageIds[$name] = $this->createSchema( $name )->getPageId();
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

	public function testGetReadableSchemaNamesYieldsEverySchemaKeyedByPageId(): void {
		$this->assertSame(
			[
				$this->pageIds['SchemaNameLookupTest1'] => 'SchemaNameLookupTest1',
				$this->pageIds['SchemaNameLookupTest21'] => 'SchemaNameLookupTest21',
				$this->pageIds['SchemaNameLookupTest22'] => 'SchemaNameLookupTest22',
				$this->pageIds['SchemaNameLookupTest3'] => 'SchemaNameLookupTest3',
			],
			array_map(
				static fn ( TitleValue $title ): string => $title->getText(),
				iterator_to_array( $this->getLookup()->getReadableSchemaNames() )
			)
		);
	}

	public function testGetReadableSchemaNamesStartsAfterTheGivenPageId(): void {
		$this->assertSame(
			[
				$this->pageIds['SchemaNameLookupTest22'] => 'SchemaNameLookupTest22',
				$this->pageIds['SchemaNameLookupTest3'] => 'SchemaNameLookupTest3',
			],
			array_map(
				static fn ( TitleValue $title ): string => $title->getText(),
				iterator_to_array(
					$this->getLookup()->getReadableSchemaNames( $this->pageIds['SchemaNameLookupTest21'] )
				)
			)
		);
	}

	public function testGetReadableSchemaNamesOmitsUnreadableSchemas(): void {
		// GateHiddenSchema is created before GateVisibleSchema so the denied row sits mid-list. A
		// denied Schema must not be yielded at all: the summaries endpoint fills its page from this
		// iterable and builds its cursor from the yielded keys, so a skipped Schema neither takes
		// page space nor becomes inferable from the pagination (#1062).
		$this->createSchema( 'GateHiddenSchema' );
		$visibleId = $this->createSchema( 'GateVisibleSchema' )->getPageId();

		$denyHidden = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$page === null || $page->getDBkey() !== 'GateHiddenSchema';

		$this->assertSame(
			[
				$this->pageIds['SchemaNameLookupTest1'] => 'SchemaNameLookupTest1',
				$this->pageIds['SchemaNameLookupTest21'] => 'SchemaNameLookupTest21',
				$this->pageIds['SchemaNameLookupTest22'] => 'SchemaNameLookupTest22',
				$this->pageIds['SchemaNameLookupTest3'] => 'SchemaNameLookupTest3',
				$visibleId => 'GateVisibleSchema',
			],
			array_map(
				static fn ( TitleValue $title ): string => $title->getText(),
				iterator_to_array( $this->getLookup( $this->mockRegisteredAuthority( $denyHidden ) )->getReadableSchemaNames() )
			)
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
