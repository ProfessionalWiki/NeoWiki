<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherMap;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore
 * @group Database
 */
class Neo4jQueryStoreTest extends NeoWikiIntegrationTestCase {

	private const GUID_1 = 'sTestNQS1111111';
	private const GUID_2 = 'sTestNQS1111112';
	private const GUID_3 = 'sTestNQS1111113';
	private const GUID_4 = 'sTestNQS1111114';
	private const SCHEMA_ID_A = 'sTestNQS111111A';
	private const SCHEMA_ID_Z = 'sTestNQS111111Z';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->createSchema( self::SCHEMA_ID_A );
		$this->createSchema( self::SCHEMA_ID_Z );
	}

	public function testReadQueryReturnsNothingWhenDbIsEmpty(): void {
		$result = $this->newQueryStore()->runReadQuery( 'MATCH (n) RETURN n' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

	private function newQueryStore(): Neo4jQueryStore {
		return NeoWikiExtension::getInstance()->newNeo4jQueryStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID ),
				TestSchema::build( name: self::SCHEMA_ID_A ),
				TestSchema::build( name: self::SCHEMA_ID_Z )
			)
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

	public function testRunReadQueryDoesNotDeleteNodes(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
				TestSubject::build( id: self::GUID_3 ),
			)
		) );

		$this->expectException( Neo4jException::class );
		$this->expectExceptionMessage( "Delete relationship with type 'HasSubject' on database 'neo4j' is not allowed for user 'mediawiki_read' with roles [PUBLIC, reader]." );

		$store->runReadQuery( 'MATCH (n) DETACH DELETE n' );

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

	public function testRunWriteQuerySavesToDb(): void {
		$store = $this->newQueryStore();

		$store->runWriteQuery( 'CREATE (:TestNode {name: "Test"} )' );

		$result = $store->runReadQuery( 'MATCH (node:TestNode {name: "Test"}) RETURN node.name' );

		$this->assertSame(
			[
				[ 'node.name' => 'Test' ]
			],
			$result->toRecursiveArray()
		);
	}

	public function testSavesPageSubjectsWithSubjectLabel(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaId: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaId: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
				TestSubject::build( id: self::GUID_3, schemaId: new SchemaName( self::SCHEMA_ID_Z ) ),
			)
		) );

		$this->assertPageHasSubjectsWithLabels(
			[
				[ 'id' => self::GUID_1, 'labels' => [ 'Subject', self::SCHEMA_ID_A ] ],
				[ 'id' => self::GUID_2, 'labels' => [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ] ],
				[ 'id' => self::GUID_3, 'labels' => [ 'Subject', self::SCHEMA_ID_Z ] ]
			],
			42,
			$store
		);
	}

	private function assertPageHasSubjectsWithLabels( array $expectedSubjects, int $pageId, Neo4jQueryStore $store ): void {
		$result = $store->runReadQuery(
			'MATCH (page:Page {id: ' . $pageId . '})-[hs:HasSubject]->(subject)
			 RETURN subject.id as id, labels(subject) as labels
			 ORDER BY id'
		)->getResults()->toRecursiveArray();

		foreach ( $expectedSubjects as &$subject ) {
			sort( $subject['labels'] );
		}

		foreach ( $result as &$subject ) {
			sort( $subject['labels'] );
		}

		$this->assertSame( $expectedSubjects, $result );
	}

	public function testSavesPageSubjectsWithSubjectLabelAfterUpdatingPage(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaId: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaId: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
				TestSubject::build( id: self::GUID_3, schemaId: new SchemaName( self::SCHEMA_ID_Z ) ),
			)
		) );

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaId: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaId: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
				TestSubject::build( id: self::GUID_3, schemaId: new SchemaName( self::SCHEMA_ID_Z ) ),
				TestSubject::build( id: self::GUID_4, schemaId: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
			)
		) );

		$this->assertPageHasSubjectsWithLabels(
			[
				[ 'id' => self::GUID_1, 'labels' => [ 'Subject', self::SCHEMA_ID_A ] ],
				[ 'id' => self::GUID_2, 'labels' => [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ] ],
				[ 'id' => self::GUID_3, 'labels' => [ 'Subject', self::SCHEMA_ID_Z ] ],
				[ 'id' => self::GUID_4, 'labels' => [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ] ],
			],
			42,
			$store
		);
	}

}
