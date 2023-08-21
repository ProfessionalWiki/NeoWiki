<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Types\CypherMap;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore
 */
class Neo4jQueryStoreTest extends NeoWikiIntegrationTestCase {

	private const GUID_1 = '00000000-1237-0000-0000-000000000001';
	private const GUID_2 = '00000000-1237-0000-0000-000000000002';
	private const GUID_3 = '00000000-1237-0000-0000-000000000003';
	private const GUID_4 = '00000000-1237-0000-0000-000000000004';
	private LegacyLoggerSpy $logger;

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->logger = new LegacyLoggerSpy();
	}

	public function testReadQueryReturnsNothingWhenDbIsEmpty(): void {
		$result = $this->newQueryStore()->runReadQuery( 'MATCH (n) RETURN n' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

	private function newQueryStore(): Neo4jQueryStore {
		return new Neo4jQueryStore(
			NeoWikiExtension::getInstance()->getNeo4jClient(),
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID )
			),
			$this->logger
		);
	}

	public function testSavesPageIdAndTitle(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'TestPage'
			)
		) );

		$result = $store->runReadQuery( 'MATCH (page:Page {id: 42}) RETURN properties(page) as page' );

		/**
		 * @var CypherMap $first
		 */
		$first = $result->first();
		$page = $first->toRecursiveArray()['page'];

		$this->assertSame(
			42,
			$page['id']
		);

		$this->assertSame(
			'TestPage',
			$page['name']
		);
	}

	public function testSavesPageSubjects(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
				TestSubject::build( id: self::GUID_3 ),
			)
		) );

		$this->assertPageHasSubjects(
			[
				[ 'id' => self::GUID_1, 'hs' => [ 'isMain' => true ] ],
				[ 'id' => self::GUID_2, 'hs' => [ 'isMain' => false ] ],
				[ 'id' => self::GUID_3, 'hs' => [ 'isMain' => false ] ]
			],
			42,
			$store
		);
	}

	private function assertPageHasSubjects( array $expectedSubjects, int $pageId, Neo4jQueryStore $store ): void {
		$result = $store->runReadQuery(
			'
			MATCH (page:Page {id: ' . $pageId . '})-[hs:HasSubject]->(subject)
			RETURN subject.id as id, properties(hs) as hs
			ORDER BY id'
		)->getResults()->toRecursiveArray();

		$this->assertSame( $expectedSubjects, $result );
	}

	public function testSavesPageRemovesObsoleteSubjects(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
				TestSubject::build( id: self::GUID_3 ),
			)
		) );

		$store->savePage( TestPage::build(
			id: 42,
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
				TestSubject::build( id: self::GUID_4 ),
			)
		) );

		$this->assertPageHasSubjects(
			[
				[ 'id' => self::GUID_2, 'hs' => [ 'isMain' => false ] ],
				[ 'id' => self::GUID_4, 'hs' => [ 'isMain' => false ] ]
			],
			42,
			$store
		);
	}

	public function testSavingPageAndThenDeletingItLeavesNoTrace(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'TestPage'
			)
		) );

		$store->deletePage( new PageId( 42 ) );

		$result = $store->runReadQuery( 'MATCH (n) RETURN *' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

	//public function testUpdatesRelations(): void {
	//	$store = $this->newQueryStore();
	//
	//	$store->savePage( TestPage::build(
	//		mainSubject: TestSubject::build( id: self::GUID_1 ),
	//		childSubjects: new SubjectMap(
	//			TestSubject::build(
	//				id: self::GUID_2,
	//				properties: new StatementList( [
	//
	//				] )
	//			),
	//		)
	//	) );
	//
	//
	//}

	/**
	 * @dataProvider timestampConversionProvider
	 */
	public function testFormatMediaWikiTimestamp( string $mwTime, string $neoTime ): void {
		$this->assertSame(
			$neoTime,
			Neo4jQueryStore::mediaWikiTimestampToNeo4jFormat( $mwTime )
		);
	}

	public static function timestampConversionProvider(): iterable {
		yield [ '', '' ];
		yield [ '20230726163439', '2023-07-26T16:34:39' ];
		yield [ '20230101000000', '2023-01-01T00:00:00' ];
		yield [ 'invalid', '' ];
	}

}
