<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherMap;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageDateTime;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdaterFactory;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore
 * @group Database
 */
class Neo4jProjectionStoreTest extends NeoWikiIntegrationTestCase {

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
		$result = $this->readGraph( 'MATCH (n) RETURN n' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

	private function newQueryStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID ),
				TestSchema::build( name: self::SCHEMA_ID_A ),
				TestSchema::build( name: self::SCHEMA_ID_Z )
			)
		);
	}

	private function newQueryStoreForWiki( string $wikiId ): GraphDatabasePlugin {
		$extension = NeoWikiExtension::getInstance();

		return new Neo4jProjectionStore(
			client: $extension->getNeo4jClient(),
			subjectUpdaterFactory: new Neo4jSubjectUpdaterFactory(
				schemaLookup: new InMemorySchemaLookup(
					TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID )
				),
				valueBuilderRegistry: $extension->getValueBuilderRegistry(),
				logger: new NullLogger(),
				wikiId: $wikiId,
			),
			wikiId: $wikiId,
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

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN properties(page) as page' );

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

	public function testSavesWikiIdOnPageNode(): void {
		$store = $this->newQueryStoreForWiki( 'my_wiki' );

		$store->savePage( TestPage::build( id: 42 ) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN page.wiki_id AS wikiId' );

		$this->assertSame( 'my_wiki', $result->first()->toRecursiveArray()['wikiId'] );
	}

	public function testSavesWikiIdOnSubjectNodes(): void {
		$store = $this->newQueryStoreForWiki( 'my_wiki' );

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
			)
		) );

		$result = $this->readGraph(
			'MATCH (subject:Subject) RETURN subject.id AS id, subject.wiki_id AS wikiId ORDER BY id'
		)->toRecursiveArray();

		$this->assertSame(
			[
				[ 'id' => self::GUID_1, 'wikiId' => 'my_wiki' ],
				[ 'id' => self::GUID_2, 'wikiId' => 'my_wiki' ],
			],
			$result
		);
	}

	public function testPagesWithSameIdInDifferentWikisDoNotOverwriteEachOther(): void {
		$wikiA = $this->newQueryStoreForWiki( 'wiki_a' );
		$wikiB = $this->newQueryStoreForWiki( 'wiki_b' );

		$wikiA->savePage( TestPage::build( id: 42, properties: TestPageProperties::build( title: 'Page on wiki A' ) ) );
		$wikiB->savePage( TestPage::build( id: 42, properties: TestPageProperties::build( title: 'Page on wiki B' ) ) );

		$result = $this->readGraph(
			'MATCH (page:Page {id: 42}) RETURN page.wiki_id AS wikiId, page.name AS name ORDER BY wikiId'
		)->toRecursiveArray();

		$this->assertSame(
			[
				[ 'wikiId' => 'wiki_a', 'name' => 'Page on wiki A' ],
				[ 'wikiId' => 'wiki_b', 'name' => 'Page on wiki B' ],
			],
			$result
		);
	}

	public function testDeletingPageOnlyDeletesItInItsOwnWiki(): void {
		$wikiA = $this->newQueryStoreForWiki( 'wiki_a' );
		$wikiB = $this->newQueryStoreForWiki( 'wiki_b' );

		$wikiA->savePage( TestPage::build( id: 42, properties: TestPageProperties::build( title: 'Page on wiki A' ) ) );
		$wikiB->savePage( TestPage::build( id: 42, properties: TestPageProperties::build( title: 'Page on wiki B' ) ) );

		$wikiA->deletePage( new PageId( 42 ) );

		$result = $this->readGraph(
			'MATCH (page:Page {id: 42}) RETURN page.wiki_id AS wikiId, page.name AS name ORDER BY wikiId'
		)->toRecursiveArray();

		$this->assertSame(
			[
				[ 'wikiId' => 'wiki_b', 'name' => 'Page on wiki B' ],
			],
			$result
		);
	}

	public function testSubjectsAreLinkedToTheirOwnWikiPageOnly(): void {
		$wikiA = $this->newQueryStoreForWiki( 'wiki_a' );
		$wikiB = $this->newQueryStoreForWiki( 'wiki_b' );

		$wikiA->savePage( TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::GUID_1 ) ) );
		$wikiB->savePage( TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::GUID_2 ) ) );

		$result = $this->readGraph(
			'MATCH (page:Page {id: 42})-[:HasSubject]->(subject)
			 RETURN page.wiki_id AS wikiId, subject.id AS subjectId ORDER BY wikiId, subjectId'
		)->toRecursiveArray();

		$this->assertSame(
			[
				[ 'wikiId' => 'wiki_a', 'subjectId' => self::GUID_1 ],
				[ 'wikiId' => 'wiki_b', 'subjectId' => self::GUID_2 ],
			],
			$result
		);
	}

	public function testDeletingPageOnlyDeletesItsOwnWikiSubjects(): void {
		$wikiA = $this->newQueryStoreForWiki( 'wiki_a' );
		$wikiB = $this->newQueryStoreForWiki( 'wiki_b' );

		$wikiA->savePage( TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::GUID_1 ) ) );
		$wikiB->savePage( TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::GUID_2 ) ) );

		$wikiA->deletePage( new PageId( 42 ) );

		$result = $this->readGraph(
			'MATCH (subject:Subject) RETURN subject.id AS id ORDER BY id'
		)->toRecursiveArray();

		$this->assertSame(
			[ [ 'id' => self::GUID_2 ] ],
			$result
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
			42
		);
	}

	private function assertPageHasSubjects( array $expectedSubjects, int $pageId ): void {
		$result = $this->readGraph(
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
			42
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

		$result = $this->readGraph( 'MATCH (n) RETURN *' );

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
			Neo4jProjectionStore::mediaWikiTimestampToNeo4jFormat( $mwTime )
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

		$this->readGraph( 'MATCH (n) DETACH DELETE n' );

		$this->assertPageHasSubjects(
			[
				[ 'id' => self::GUID_1, 'hs' => [ 'isMain' => true ] ],
				[ 'id' => self::GUID_2, 'hs' => [ 'isMain' => false ] ],
				[ 'id' => self::GUID_3, 'hs' => [ 'isMain' => false ] ]
			],
			42
		);
	}

	public function testRunWriteQuerySavesToDb(): void {
		$this->writeGraph( 'CREATE (:TestNode {name: "Test"} )' );

		$result = $this->readGraph( 'MATCH (node:TestNode {name: "Test"}) RETURN node.name' );

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
			mainSubject: TestSubject::build( id: self::GUID_1, schemaName: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaName: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
				TestSubject::build( id: self::GUID_3, schemaName: new SchemaName( self::SCHEMA_ID_Z ) ),
			)
		) );

		$this->assertPageHasSubjectsWithLabels(
			[
				[ 'id' => self::GUID_1, 'labels' => [ 'Subject', self::SCHEMA_ID_A ] ],
				[ 'id' => self::GUID_2, 'labels' => [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ] ],
				[ 'id' => self::GUID_3, 'labels' => [ 'Subject', self::SCHEMA_ID_Z ] ]
			],
			42
		);
	}

	public function testDeletingPagePreservesSubjectReferencedByOtherSubject(): void {
		$relationPropertyName = 'locatedIn';
		$relationType = 'LocatedIn';

		$store = NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build(
					name: TestSubject::DEFAULT_SCHEMA_ID,
					properties: new PropertyDefinitions( [
						$relationPropertyName => new RelationProperty(
							core: new PropertyCore( description: '', required: false, default: null ),
							relationType: new RelationType( $relationType ),
							targetSchema: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ),
							multiple: false,
						),
					] ),
				),
			)
		);

		$store->savePage( TestPage::build( // The page with subject that will be deleted
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
		) );

		$store->savePage( TestPage::build( // The page that has a subject with relation to the to-be-deleted subject
			id: 2,
			mainSubject: TestSubject::build(
				id: self::GUID_2,
				statements: new StatementList( [
					TestStatement::buildRelation(
						property: $relationPropertyName,
						relations: [
							TestRelation::build( id: 'rTestNQS1111rr1', targetId: self::GUID_1 ),
						],
					),
				] ),
			),
		) );

		$store->deletePage( new PageId( 1 ) );

		$result = $this->readGraph(
			'MATCH (subject {id: "' . self::GUID_1 . '"}) RETURN subject'
		);
		$this->assertFalse( $result->isEmpty(), 'Subject referenced by another subject should not be deleted' );

		$relationResult = $this->readGraph(
			'MATCH ({id: "' . self::GUID_2 . '"})-[r:' . $relationType . ']->({id: "' . self::GUID_1 . '"}) RETURN r'
		);
		$this->assertFalse( $relationResult->isEmpty(), 'Relation to preserved subject should still exist' );
	}

	private function assertPageHasSubjectsWithLabels( array $expectedSubjects, int $pageId ): void {
		$result = $this->readGraph(
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

	public function testSavesCreationTimeAsNeo4jDatetime(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( creationTime: '20230726163439' )
		) );

		$result = $this->readGraph(
			'MATCH (page:Page {id: 42}) RETURN page.creationTime = datetime("2023-07-26T16:34:39") AS isDatetime'
		);

		$this->assertTrue(
			$result->first()->toRecursiveArray()['isDatetime'],
			'creationTime should be stored as a Neo4j datetime'
		);
	}

	public function testSavesLastUpdatedAsNeo4jDatetime(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( modificationTime: '20240315100000' )
		) );

		$result = $this->readGraph(
			'MATCH (page:Page {id: 42}) RETURN page.lastUpdated = datetime("2024-03-15T10:00:00") AS isDatetime'
		);

		$this->assertTrue(
			$result->first()->toRecursiveArray()['isDatetime'],
			'lastUpdated should be stored as a Neo4j datetime'
		);
	}

	public function testSavesLastEditor(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( lastEditor: 'JohnDoe' )
		) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN page.lastEditor AS lastEditor' );

		$this->assertSame( 'JohnDoe', $result->first()->toRecursiveArray()['lastEditor'] );
	}

	public function testSavesCategories(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( categories: [ 'CatA', 'CatB' ] )
		) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN page.categories AS categories' );

		$this->assertSame( [ 'CatA', 'CatB' ], $result->first()->toRecursiveArray()['categories'] );
	}

	public function testSavesPageExtraProperties(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'TestPage',
				extraProperties: [
					'customFlag' => true,
					'customScore' => 99,
					'customLabel' => 'hello',
				]
			)
		) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN properties(page) as page' );

		$page = $result->first()->toRecursiveArray()['page'];

		$this->assertTrue( $page['customFlag'] );
		$this->assertSame( 99, $page['customScore'] );
		$this->assertSame( 'hello', $page['customLabel'] );
	}

	public function testSavesExtensionProvidedDateTimeAsNeo4jDatetime(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: new PageProperties( [
				'name' => 'TestPage',
				'creationTime' => new PageDateTime( '20230726163439' ),
				'modificationTime' => new PageDateTime( '20230726163439' ),
				'approvalTime' => new PageDateTime( '20240101120000' ),
			] )
		) );

		$result = $this->readGraph(
			'MATCH (page:Page {id: 42}) RETURN page.approvalTime = datetime("2024-01-01T12:00:00") AS isDatetime'
		);

		$this->assertTrue(
			$result->first()->toRecursiveArray()['isDatetime'],
			'Extension-provided PageDateTime should be stored as a Neo4j datetime'
		);
	}

	public function testSavesPageWithEmptyExtraProperties(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( title: 'TestPage' )
		) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN properties(page) as page' );

		$page = $result->first()->toRecursiveArray()['page'];

		$this->assertSame( 42, $page['id'] );
		$this->assertSame( 'TestPage', $page['name'] );
	}

	public function testSavesPageSubjectsWithSubjectLabelAfterUpdatingPage(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaName: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaName: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
				TestSubject::build( id: self::GUID_3, schemaName: new SchemaName( self::SCHEMA_ID_Z ) ),
			)
		) );

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaName: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaName: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
				TestSubject::build( id: self::GUID_3, schemaName: new SchemaName( self::SCHEMA_ID_Z ) ),
				TestSubject::build( id: self::GUID_4, schemaName: new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
			)
		) );

		$this->assertPageHasSubjectsWithLabels(
			[
				[ 'id' => self::GUID_1, 'labels' => [ 'Subject', self::SCHEMA_ID_A ] ],
				[ 'id' => self::GUID_2, 'labels' => [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ] ],
				[ 'id' => self::GUID_3, 'labels' => [ 'Subject', self::SCHEMA_ID_Z ] ],
				[ 'id' => self::GUID_4, 'labels' => [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ] ],
			],
			42
		);
	}

}
