<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
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
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jConstraintUpdater;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdaterFactory;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jWriteQueryEngine;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jProjectionStore
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jNodeLabels
 * @group Database
 */
class Neo4jProjectionStoreTest extends NeoWikiIntegrationTestCase {

	private const GUID_1 = 'sTestNQS1111111';
	private const GUID_2 = 'sTestNQS1111112';
	private const GUID_3 = 'sTestNQS1111113';
	private const GUID_4 = 'sTestNQS1111114';
	private const GUID_5 = 'sTestNQS1111115';
	private const SCHEMA_ID_A = 'sTestNQS111111A';
	private const SCHEMA_ID_Z = 'sTestNQS111111Z';
	private const WIKI_ID = 'my_wiki';

	public function setUp(): void {
		// No Schema pages: these tests drive the store directly, with the Schemas their subjects reference
		// injected through an InMemorySchemaLookup, and assert over a graph holding only what they write.
		$this->setUpNeo4j();
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID ),
				TestSchema::build( name: self::SCHEMA_ID_A ),
				TestSchema::build( name: self::SCHEMA_ID_Z )
			)
		);
	}

	private function newProjectionStoreForWiki( string $wikiId ): GraphDatabasePlugin {
		$extension = NeoWikiExtension::getInstance();

		return new Neo4jProjectionStore(
			client: $extension->getNeo4jClient(),
			subjectUpdaterFactory: new Neo4jSubjectUpdaterFactory(
				schemaResolver: TestSources::newSchemaResolver( new InMemorySchemaLookup(
					TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID )
				) ),
				valueBuilderRegistry: $extension->getValueBuilderRegistry(),
				logger: new NullLogger(),
				wikiId: $wikiId,
			),
			constraintUpdater: new Neo4jConstraintUpdater( new Neo4jWriteQueryEngine( $extension->getNeo4jClient() ) ),
			wikiId: $wikiId,
		);
	}

	public function testSavesPageIdAndTitle(): void {
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStoreForWiki( 'my_wiki' );

		$store->savePage( TestPage::build( id: 42 ) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN page.wiki_id AS wikiId' );

		$this->assertSame( 'my_wiki', $result->first()->toRecursiveArray()['wikiId'] );
	}

	public function testSavesWikiIdOnSubjectNodes(): void {
		$store = $this->newProjectionStoreForWiki( 'my_wiki' );

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
		$wikiA = $this->newProjectionStoreForWiki( 'wiki_a' );
		$wikiB = $this->newProjectionStoreForWiki( 'wiki_b' );

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
		$wikiA = $this->newProjectionStoreForWiki( 'wiki_a' );
		$wikiB = $this->newProjectionStoreForWiki( 'wiki_b' );

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
		$wikiA = $this->newProjectionStoreForWiki( 'wiki_a' );
		$wikiB = $this->newProjectionStoreForWiki( 'wiki_b' );

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
		$wikiA = $this->newProjectionStoreForWiki( 'wiki_a' );
		$wikiB = $this->newProjectionStoreForWiki( 'wiki_b' );

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
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

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

	public function testSavingPageWithoutAReferencedSubjectPreservesIncomingRelations(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
		) );

		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_1, 'rTestNQS1111rr1' );
	}

	public function testSavingPageWithoutAReferencedSubjectReducesItToAStub(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
		) );

		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectIsStub( self::GUID_1 );
	}

	public function testSavingPageWithoutAnUnreferencedSubjectDeletesIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
			)
		) );

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
		) );

		$this->assertSubjectDoesNotExist( self::GUID_2 );
	}

	public function testDeletingPageReducesAReferencedSubjectToAStub(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
		) );

		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		$store->deletePage( new PageId( 1 ) );

		$this->assertSubjectIsStub( self::GUID_1 );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_1, 'rTestNQS1111rr1' );
	}

	public function testSavingASubjectUpgradesItsStubInPlace(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1, label: 'Real subject' ),
		) );

		$result = $this->readGraph(
			'MATCH (subject {id: $id}) RETURN subject.name AS name, labels(subject) AS labels',
			[ 'id' => self::GUID_1 ]
		);

		$this->assertCount( 1, $result->toArray(), 'Saving the real subject should not create a duplicate node' );

		$row = $result->first()->toRecursiveArray();
		$labels = $row['labels'];
		sort( $labels );

		$this->assertSame( [ 'Subject', TestSubject::DEFAULT_SCHEMA_ID ], $labels );
		$this->assertSame( 'Real subject', $row['name'] );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_1, 'rTestNQS1111rr1' );
	}

	/**
	 * A save creates the subject's node and then matches it again to attach its relations, its page and
	 * its schema label. Every one of those matches addresses it as a :Subject, so the node has to carry
	 * that label from creation: were the label added at the end of the save instead, the relation step
	 * would not find the node it just created and would create a second one under the same id.
	 */
	public function testSavingANewSubjectWithARelationCreatesASingleNodeForIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		$this->assertSingleNodeWithId( self::GUID_2 );
	}

	/**
	 * Both relations are created by one query, so the row that runs second has to match the target
	 * node the first row created rather than create a second one under the same id. This also pins
	 * that the relations of more than one subject of a page are created at all.
	 */
	public function testTwoSubjectsOnOnePageRelatingToTheSameNewTargetCreateOneStub(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_3, 'rTestNQS1111rr1' ),
			childSubjects: new SubjectMap(
				$this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_3, 'rTestNQS1111rr2' ),
			)
		) );

		$this->assertSingleNodeWithId( self::GUID_3 );
		$this->assertRelationExists( self::GUID_1, 'LocatedIn', self::GUID_3, 'rTestNQS1111rr1' );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_3, 'rTestNQS1111rr2' );
	}

	/**
	 * Only an XML import can store such a target, the write guard refusing it on every API path. The
	 * edge is dropped rather than MERGEd, which would stamp a foreign Subject's stub with this wiki's
	 * `wiki_id` (graph-model.md).
	 */
	public function testRelationToASubjectOfAnotherSourceIsNotProjected(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 3,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, 'otherwiki:Q42', 'rTestNQS1111rr9' ),
		) );

		$this->assertNoNodeWithId( 'otherwiki:Q42' );
		$this->assertSingleNodeWithId( self::GUID_1 );
	}

	private function assertNoNodeWithId( string $id ): void {
		$result = $this->readGraph( 'MATCH (node {id: $id}) RETURN count(node) AS count', [ 'id' => $id ] );

		$this->assertSame( 0, $result->first()->toRecursiveArray()['count'] );
	}

	private function assertSingleNodeWithId( string $id ): void {
		// Deliberately unlabeled, so a node that failed to get the Subject label still counts.
		$result = $this->readGraph( 'MATCH (node {id: $id}) RETURN count(node) AS count', [ 'id' => $id ] );

		$this->assertSame( 1, $result->first()->toRecursiveArray()['count'] );
	}

	public function testReducingReferencedSubjectToStubKeepsIncomingRelationsButStripsOutgoingRelationsAndProperties(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1 has a scalar property and two outgoing relations (to GUID_3 and GUID_4).
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build(
				id: self::GUID_1,
				statements: new StatementList( [
					TestStatement::build( property: 'nickname', value: 'Ada' ),
					TestStatement::buildRelation(
						property: 'locatedIn',
						relations: [
							TestRelation::build( id: 'rTestNQS1111rrB', targetId: self::GUID_3 ),
							TestRelation::build( id: 'rTestNQS1111rrE', targetId: self::GUID_4 ),
						],
					),
				] ),
			),
		) );

		// GUID_2 and GUID_5, on their own pages, each hold an incoming relation to GUID_1.
		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rCA' ),
		) );
		$store->savePage( TestPage::build(
			id: 3,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_5, self::GUID_1, 'rTestNQS1111rDA' ),
		) );

		$this->assertTrue(
			$this->subjectHasProperty( self::GUID_1, 'nickname' ),
			'Precondition: the subject has a projected scalar property before being stubbed'
		);
		$this->assertOutgoingRelationCount( self::GUID_1, 2, 'Precondition: the subject has two outgoing relations' );

		// Remove GUID_1 from its page while GUID_2 and GUID_5 still reference it.
		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectIsStub( self::GUID_1 );
		$this->assertOutgoingRelationCount( self::GUID_1, 0 );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_1, 'rTestNQS1111rCA' );
		$this->assertRelationExists( self::GUID_5, 'LocatedIn', self::GUID_1, 'rTestNQS1111rDA' );
	}

	public function testFlippingASubjectBetweenMainAndChildLeavesASingleHasSubjectEdge(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2 ),
			)
		) );

		// Swap the roles: GUID_2 becomes the main subject and GUID_1 becomes a child.
		// The HasSubject relation carries the isMain flag, so re-saving must not leave a
		// second, stale HasSubject edge behind for either subject.
		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_2 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_1 ),
			)
		) );

		$this->assertPageHasSubjects(
			[
				[ 'id' => self::GUID_1, 'hs' => [ 'isMain' => false ] ],
				[ 'id' => self::GUID_2, 'hs' => [ 'isMain' => true ] ],
			],
			42
		);
	}

	public function testRemovingASelfReferencingSubjectDeletesItRatherThanStubbingIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1 holds a relation to itself and nothing else references it.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		// Removing it from its page must delete it: a self-loop is not an external reference,
		// so keeping it as a stub would leave an unreachable orphan node.
		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectDoesNotExist( self::GUID_1 );
	}

	public function testRemovingMutuallyReferencingSubjectsFromTheirPageDeletesBoth(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1 and GUID_2 reference each other and nothing else references either of them.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
			childSubjects: new SubjectMap(
				$this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr2' ),
			)
		) );

		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectDoesNotExist( self::GUID_1 );
		$this->assertSubjectDoesNotExist( self::GUID_2 );
	}

	public function testRemovingAReferenceCycleFromItsPageDeletesEverySubjectInIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1 -> GUID_2 -> GUID_3 -> GUID_1, with no reference from outside the cycle.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
			childSubjects: new SubjectMap(
				$this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_3, 'rTestNQS1111rr2' ),
				$this->buildSubjectWithLocationRelation( self::GUID_3, self::GUID_1, 'rTestNQS1111rr3' ),
			)
		) );

		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectDoesNotExist( self::GUID_1 );
		$this->assertSubjectDoesNotExist( self::GUID_2 );
		$this->assertSubjectDoesNotExist( self::GUID_3 );
	}

	public function testRemovingTheLastSubjectReferencingAStubDeletesTheStub(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1's relation targets a subject that does not exist, creating GUID_2 as a stub.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
		) );

		$this->assertSubjectIsStub( self::GUID_2 );

		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectDoesNotExist( self::GUID_2 );
	}

	public function testDeletingThePagesOfMutuallyReferencingSubjectsDeletesBoth(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1 and GUID_2 live on separate pages and reference each other.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
		) );
		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr2' ),
		) );

		// Deleting the first page turns GUID_1 into a stub that GUID_2 still references.
		$store->deletePage( new PageId( 1 ) );
		$this->assertSubjectIsStub( self::GUID_1 );

		$store->deletePage( new PageId( 2 ) );

		$this->assertSubjectDoesNotExist( self::GUID_1 );
		$this->assertSubjectDoesNotExist( self::GUID_2 );
	}

	public function testRemovingTheLastReferenceDeletesAStubWrittenByAnotherWiki(): void {
		$wikiA = $this->newProjectionStoreWithLocationRelation( 'wiki_a' );
		$wikiB = $this->newProjectionStoreWithLocationRelation( 'wiki_b' );

		// GUID_1 on wiki A relates to GUID_2, creating it as a stub.
		$wikiA->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
		) );

		// Wiki B claims GUID_2 and drops it again, leaving a stub that carries wiki B's wiki_id.
		$wikiB->savePage( TestPage::build( id: 2, mainSubject: TestSubject::build( id: self::GUID_2 ) ) );
		$wikiB->deletePage( new PageId( 2 ) );

		// Removing GUID_1 strips that stub of its last reference, whichever wiki last wrote it.
		$wikiA->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectDoesNotExist( self::GUID_2 );
	}

	public function testRemovingASubjectLeavesAnUnlinkedFullSubjectAlone(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_2 relates to GUID_1, so removing GUID_2 later makes GUID_1 a sweep candidate.
		$store->savePage( TestPage::build( id: 1, mainSubject: TestSubject::build( id: self::GUID_1 ) ) );
		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		// Re-saving GUID_1 under a schema this store's lookup does not know makes the projection skip it,
		// so it keeps its data while losing its HasSubject relation. newProjectionStoreWithLocationRelation
		// hands its store an InMemorySchemaLookup holding the default schema alone, so SCHEMA_ID_A does not
		// resolve here despite setUp creating a Schema page for it: no store in this class reads those pages.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaName: new SchemaName( self::SCHEMA_ID_A ) ),
		) );

		// The skip is what gives this test its teeth, so assert it happened rather than assuming it.
		$this->assertSubjectIsNotStub( self::GUID_1 );
		$this->assertHasNoIncomingHasSubjectRelation( self::GUID_1 );

		// Removing GUID_2 takes GUID_1's last incoming relation with it, leaving a subject that has
		// no incoming relation at all but still carries data.
		$store->savePage( TestPage::build( id: 2 ) );

		// Asserting the data survives, not merely the node: a sweep that reduced unreachable
		// candidates to stubs instead of deleting them would leave a node behind either way.
		$this->assertSubjectIsNotStub( self::GUID_1 );
	}

	public function testRemovingASubjectLeavesAnUnlinkedLabellessSubjectAlone(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// The same shape as the test above, with a Subject nobody named. A sweep that took the absence
		// of a name for the mark of a stub would delete this one and its Statements with it.
		$store->savePage( TestPage::build(
			id: 1,
			childSubjects: new SubjectMap( TestSubject::build( id: self::GUID_1, label: null ) )
		) );
		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_1, 'rTestNQS1111rr1' ),
		) );

		$store->savePage( TestPage::build(
			id: 1,
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_1, label: null, schemaName: new SchemaName( self::SCHEMA_ID_A ) )
			)
		) );

		$this->assertHasNoIncomingHasSubjectRelation( self::GUID_1 );

		$store->savePage( TestPage::build( id: 2 ) );

		$this->assertSubjectExists( self::GUID_1 );
	}

	public function testDroppingTheOnlyRelationToAStubDeletesTheStub(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		// GUID_1's relation targets a subject that does not exist, creating GUID_2 as a stub.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
		) );

		$this->assertSubjectIsStub( self::GUID_2 );

		// Re-saving GUID_1 without the relation leaves the stub with nothing pointing at it.
		$store->savePage( TestPage::build( id: 1, mainSubject: TestSubject::build( id: self::GUID_1 ) ) );

		$this->assertSubjectDoesNotExist( self::GUID_2 );
	}

	public function testRetargetingTheOnlyRelationToAStubDeletesTheStub(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_2, 'rTestNQS1111rr1' ),
		) );

		// The same relation now points at GUID_3, so GUID_2 loses its only referrer.
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_3, 'rTestNQS1111rr1' ),
		) );

		$this->assertSubjectDoesNotExist( self::GUID_2 );
		$this->assertSubjectIsStub( self::GUID_3 );
	}

	public function testDroppingOneOfTwoRelationsToAStubKeepsIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$this->saveTwoPagesReferencingStub( $store );

		// Dropping page 1's relation makes the stub a sweep candidate, but page 2 still references it.
		$store->savePage( TestPage::build( id: 1, mainSubject: TestSubject::build( id: self::GUID_1 ) ) );

		$this->assertSubjectIsStub( self::GUID_3 );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_3, 'rTestNQS1111rr2' );
	}

	public function testDeletingOneOfTwoPagesReferencingAStubKeepsIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$this->saveTwoPagesReferencingStub( $store );

		// Deleting page 1 removes GUID_1 outright, so the sweep gets the stub from the deleted
		// subject's relation targets rather than from a relation edit. Page 2 still references it.
		$store->deletePage( new PageId( 1 ) );

		$this->assertSubjectIsStub( self::GUID_3 );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_3, 'rTestNQS1111rr2' );
	}

	public function testStubbingOneOfTwoSubjectsReferencingAStubKeepsIt(): void {
		$store = $this->newProjectionStoreWithLocationRelation();

		$this->saveTwoPagesReferencingStub( $store );

		// GUID_4 references GUID_1, so removing GUID_1 reduces it to a stub instead of deleting it.
		// That drops its relation to GUID_3, which page 2 still references.
		$store->savePage( TestPage::build(
			id: 3,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_4, self::GUID_1, 'rTestNQS1111rr3' ),
		) );

		$store->savePage( TestPage::build( id: 1 ) );

		$this->assertSubjectIsStub( self::GUID_1 );
		$this->assertSubjectIsStub( self::GUID_3 );
		$this->assertRelationExists( self::GUID_2, 'LocatedIn', self::GUID_3, 'rTestNQS1111rr2' );
	}

	/**
	 * GUID_1 and GUID_2 live on separate pages and both relate to GUID_3, which exists only as the
	 * stub their relations created. Removing either referrer leaves the other one pointing at it.
	 */
	private function saveTwoPagesReferencingStub( GraphDatabasePlugin $store ): void {
		$store->savePage( TestPage::build(
			id: 1,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_1, self::GUID_3, 'rTestNQS1111rr1' ),
		) );
		$store->savePage( TestPage::build(
			id: 2,
			mainSubject: $this->buildSubjectWithLocationRelation( self::GUID_2, self::GUID_3, 'rTestNQS1111rr2' ),
		) );

		$this->assertSubjectIsStub( self::GUID_3 );
	}

	private function newProjectionStoreWithLocationRelation( string $wikiId = self::WIKI_ID ): GraphDatabasePlugin {
		$extension = NeoWikiExtension::getInstance();

		return new Neo4jProjectionStore(
			client: $extension->getNeo4jClient(),
			subjectUpdaterFactory: new Neo4jSubjectUpdaterFactory(
				schemaResolver: TestSources::newSchemaResolver( new InMemorySchemaLookup(
					TestSchema::build(
						name: TestSubject::DEFAULT_SCHEMA_ID,
						properties: new PropertyDefinitions( [
							'locatedIn' => new RelationProperty(
								core: new PropertyCore( description: '', required: false, default: null ),
								relationType: new RelationType( 'LocatedIn' ),
								targetSchema: SchemaReference::local( new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
								multiple: false,
							),
						] ),
					),
				) ),
				valueBuilderRegistry: $extension->getValueBuilderRegistry(),
				logger: new NullLogger(),
				wikiId: $wikiId,
			),
			constraintUpdater: new Neo4jConstraintUpdater( new Neo4jWriteQueryEngine( $extension->getNeo4jClient() ) ),
			wikiId: $wikiId,
		);
	}

	private function buildSubjectWithLocationRelation( string $id, string $targetId, string $relationId ): Subject {
		return TestSubject::build(
			id: $id,
			statements: new StatementList( [
				TestStatement::buildRelation(
					property: 'locatedIn',
					relations: [
						TestRelation::build( id: $relationId, targetId: $targetId ),
					],
				),
			] ),
		);
	}

	private function assertRelationExists(
		string $fromSubjectId,
		string $relationType,
		string $toSubjectId,
		?string $expectedRelationId = null
	): void {
		$result = $this->readGraph(
			'MATCH ({id: $from})-[relation:' . $relationType . ']->({id: $to}) RETURN relation.id AS id',
			[ 'from' => $fromSubjectId, 'to' => $toSubjectId ]
		);

		$this->assertFalse(
			$result->isEmpty(),
			"Relation {$fromSubjectId}-[{$relationType}]->{$toSubjectId} should exist"
		);

		// Readers reconcile relations by their id, so a relation that survives by endpoints and type
		// but loses its id is broken. Assert the id is preserved when the caller pins it down.
		if ( $expectedRelationId !== null ) {
			$this->assertSame(
				$expectedRelationId,
				$result->first()->toRecursiveArray()['id'],
				"Relation {$fromSubjectId}-[{$relationType}]->{$toSubjectId} should keep its id"
			);
		}
	}

	private function subjectHasProperty( string $subjectId, string $property ): bool {
		$result = $this->readGraph(
			'MATCH (subject {id: $id}) RETURN $property IN keys(subject) AS hasProperty',
			[ 'id' => $subjectId, 'property' => $property ]
		);

		return $result->first()->toRecursiveArray()['hasProperty'];
	}

	private function assertOutgoingRelationCount( string $subjectId, int $expected, string $message = '' ): void {
		$result = $this->readGraph(
			'MATCH ({id: $id})-[relation]->() RETURN count(relation) AS count',
			[ 'id' => $subjectId ]
		);

		$this->assertSame( $expected, $result->first()->toRecursiveArray()['count'], $message );
	}

	private function assertSubjectIsStub( string $subjectId ): void {
		$result = $this->readGraph(
			'MATCH (subject {id: $id})
				RETURN labels(subject) AS labels, subject.id AS id, subject.wiki_id AS wikiId, size(keys(subject)) AS propertyCount',
			[ 'id' => $subjectId ]
		);

		$this->assertFalse( $result->isEmpty(), "Stub subject {$subjectId} should exist" );

		$row = $result->first()->toRecursiveArray();

		$this->assertSame( [ 'Subject' ], $row['labels'], "Stub {$subjectId} should keep only the Subject label" );
		$this->assertSame( $subjectId, $row['id'] );
		$this->assertSame( self::WIKI_ID, $row['wikiId'], "Stub {$subjectId} should keep its wiki_id" );
		$this->assertSame( 2, $row['propertyCount'], "Stub {$subjectId} should keep only the id and wiki_id properties" );
	}

	private function assertSubjectExists( string $subjectId ): void {
		$result = $this->readGraph( 'MATCH (subject {id: $id}) RETURN subject', [ 'id' => $subjectId ] );

		$this->assertFalse( $result->isEmpty(), "Subject {$subjectId} should exist" );
	}

	private function assertSubjectName( ?string $expected, string $subjectId ): void {
		$result = $this->readGraph(
			'MATCH (subject {id: $id}) RETURN subject.name AS name',
			[ 'id' => $subjectId ]
		);

		$this->assertFalse( $result->isEmpty(), "Subject {$subjectId} should exist" );
		$this->assertSame( $expected, $result->first()->toRecursiveArray()['name'] );
	}

	private function assertSubjectDoesNotExist( string $subjectId ): void {
		$result = $this->readGraph( 'MATCH (subject {id: $id}) RETURN subject', [ 'id' => $subjectId ] );

		$this->assertTrue( $result->isEmpty(), "Subject {$subjectId} should not exist" );
	}

	/**
	 * The inverse of assertSubjectIsStub: the node exists and still carries the data a stub sheds.
	 * Asserting mere existence would not distinguish the two.
	 */
	private function assertSubjectIsNotStub( string $subjectId ): void {
		$result = $this->readGraph(
			'MATCH (subject {id: $id}) RETURN labels(subject) AS labels, subject.name AS name',
			[ 'id' => $subjectId ]
		);

		$this->assertFalse( $result->isEmpty(), "Subject {$subjectId} should exist" );

		$row = $result->first()->toRecursiveArray();

		$this->assertNotNull( $row['name'], "Subject {$subjectId} should keep its name" );
		$this->assertContains(
			TestSubject::DEFAULT_SCHEMA_ID,
			$row['labels'],
			"Subject {$subjectId} should keep its Schema label"
		);
	}

	private function assertHasNoIncomingHasSubjectRelation( string $subjectId ): void {
		$result = $this->readGraph(
			'MATCH (subject {id: $id}) RETURN EXISTS { ()-[:HasSubject]->(subject) } AS isLinked',
			[ 'id' => $subjectId ]
		);

		$this->assertFalse(
			$result->first()->toRecursiveArray()['isLinked'],
			"Subject {$subjectId} should have no HasSubject relation"
		);
	}

	public function testSavingPageAndThenDeletingItLeavesNoTrace(): void {
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

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

	public function testSavesPageSubjectsWithSubjectLabel(): void {
		$store = $this->newProjectionStore();

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

	public function testMainSubjectWithoutALabelTakesThePageNameAsItsNodeName(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( title: 'Help:Unnamed topic' ),
			mainSubject: TestSubject::build( id: self::GUID_1, label: null ),
		) );

		$this->assertSubjectName( 'Help:Unnamed topic', self::GUID_1 );
	}

	public function testChildSubjectWithoutALabelGetsNoNodeName(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			childSubjects: new SubjectMap( TestSubject::build( id: self::GUID_2, label: null ) )
		) );

		$this->assertSubjectName( null, self::GUID_2 );
	}

	public function testStoredLabelWinsOverThePageName(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( title: 'Page name' ),
			mainSubject: TestSubject::build( id: self::GUID_1, label: 'Chosen label' ),
		) );

		$this->assertSubjectName( 'Chosen label', self::GUID_1 );
	}

	public function testClearingALabelRemovesTheNodeNameOfAChildSubject(): void {
		$store = $this->newProjectionStore();

		$page = fn ( ?string $label ) => TestPage::build(
			id: 42,
			childSubjects: new SubjectMap( TestSubject::build( id: self::GUID_2, label: $label ) )
		);

		$store->savePage( $page( 'Named for now' ) );
		$store->savePage( $page( null ) );

		$this->assertSubjectName( null, self::GUID_2 );
	}

	public function testStatementCalledNameDoesNotBecomeTheNodeNameOfALabellessSubject(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			childSubjects: new SubjectMap( TestSubject::build(
				id: self::GUID_2,
				label: null,
				statements: new StatementList( [ TestStatement::build( property: 'name', value: new StringValue( 'Impostor' ) ) ] )
			) )
		) );

		$this->assertSubjectName( null, self::GUID_2 );
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
							targetSchema: SchemaReference::local( new SchemaName( TestSubject::DEFAULT_SCHEMA_ID ) ),
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
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( lastEditor: 'JohnDoe' )
		) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN page.lastEditor AS lastEditor' );

		$this->assertSame( 'JohnDoe', $result->first()->toRecursiveArray()['lastEditor'] );
	}

	public function testSavesCategories(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( categories: [ 'CatA', 'CatB' ] )
		) );

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN page.categories AS categories' );

		$this->assertSame( [ 'CatA', 'CatB' ], $result->first()->toRecursiveArray()['categories'] );
	}

	public function testSavesPageExtraProperties(): void {
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

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
		$store = $this->newProjectionStore();

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

	/**
	 * The only case that exercises label removal: every other save either keeps the Schema or adds a
	 * subject. The two Schemas are swapped rather than changed in one direction, so a save that mixed
	 * up which labels belong to which subject would still produce the right label set overall.
	 */
	public function testChangingSubjectSchemasReplacesTheirSchemaLabels(): void {
		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaName: new SchemaName( self::SCHEMA_ID_A ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaName: new SchemaName( self::SCHEMA_ID_Z ) ),
			)
		) );

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1, schemaName: new SchemaName( self::SCHEMA_ID_Z ) ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_2, schemaName: new SchemaName( self::SCHEMA_ID_A ) ),
			)
		) );

		$this->assertPageHasSubjectsWithLabels(
			[
				[ 'id' => self::GUID_1, 'labels' => [ 'Subject', self::SCHEMA_ID_Z ] ],
				[ 'id' => self::GUID_2, 'labels' => [ 'Subject', self::SCHEMA_ID_A ] ],
			],
			42
		);
	}

}
