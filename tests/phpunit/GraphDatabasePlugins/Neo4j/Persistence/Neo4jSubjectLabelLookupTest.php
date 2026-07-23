<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Cypher;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectLabelLookup
 */
class Neo4jSubjectLabelLookupTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID_1 = 'sTestSLL1111111';
	private const string SUBJECT_ID_2 = 'sTestSLL1111112';

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReturnsEmptyArrayOnEmptyGraph(): void {
		$this->assertSame( [], $this->getSubjectLabelsMatching( 'foo' ) );
	}

	public function testFindsSubjectsMatchingPrefix(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: self::SUBJECT_ID_1, label: new SubjectLabel( 'Apple Pie' ) ),
			TestSubject::build( id: self::SUBJECT_ID_2, label: new SubjectLabel( 'Apple Crumble' ) ),
		) );

		$results = $this->getSubjectLabelsMatching( 'Apple' );

		$this->assertCount( 2, $results );
		$this->assertContainsEquals(
			new SubjectLabelLookupResult( self::SUBJECT_ID_1, 'Apple Pie' ),
			$results
		);
		$this->assertContainsEquals(
			new SubjectLabelLookupResult( self::SUBJECT_ID_2, 'Apple Crumble' ),
			$results
		);
	}

	public function testDoesNotFindNonMatchingSubjects(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build(
				id: self::SUBJECT_ID_1,
				label: new SubjectLabel( 'Banana' )
			)
		) );

		$this->assertSame( [], $this->getSubjectLabelsMatching( 'Apple' ) );
	}

	public function testCaseInsensitiveSearch(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build(
				id: self::SUBJECT_ID_1,
				label: new SubjectLabel( 'Apple' )
			)
		) );

		$results = $this->getSubjectLabelsMatching( 'apple' );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Apple', $results[0]->label );
	}

	public function testLimitIsRespected(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: 'sTestSLL1111113', label: new SubjectLabel( 'Apple 1' ) ),
			TestSubject::build( id: 'sTestSLL1111114', label: new SubjectLabel( 'Apple 2' ) ),
			TestSubject::build( id: 'sTestSLL1111115', label: new SubjectLabel( 'Apple 3' ) ),
		) );

		$results = $this->getSubjectLabelsMatching( 'Apple', 2 );

		$this->assertCount( 2, $results );
	}

	public function testFiltersBySchema(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: 'sTestSLL1111116', label: new SubjectLabel( 'Apple Pie' ), schemaName: new SchemaName( 'Recipe' ) ),
			TestSubject::build( id: 'sTestSLL1111117', label: new SubjectLabel( 'Apple Tree' ), schemaName: new SchemaName( 'Plant' ) ),
			TestSubject::build( id: 'sTestSLL1111118', label: new SubjectLabel( 'Apple Inc.' ), schemaName: new SchemaName( 'Company' ) ),
		) );

		$results = $this->newLookup()->getSubjectLabelsMatching( 'Apple', 10, 'Recipe' );

		$this->assertCount( 1, $results );
		$this->assertContainsEquals( new SubjectLabelLookupResult( 'sTestSLL1111116', 'Apple Pie' ), $results );
	}

	public function testDoesNotReturnSubjectsFromOtherSchemas(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: 'sTestSLL1111119', label: new SubjectLabel( 'Apple Tree' ), schemaName: new SchemaName( 'Plant' ) ),
		) );

		$this->assertSame( [], $this->newLookup()->getSubjectLabelsMatching( 'Apple', 10, 'Recipe' ) );
	}

	public function testExcludesSubjectsFromOtherWikis(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: self::SUBJECT_ID_1, label: new SubjectLabel( 'Apple Pie' ) ),
		) );

		$otherWikiId = $this->currentWikiId() . '-other';
		$this->createSubjectNode( id: 'sTestSLL2222221', name: 'Apple Crumble', wikiId: $otherWikiId );
		$this->createSubjectNode( id: 'sTestSLL2222222', name: 'Apple Pie', wikiId: $otherWikiId );
		$this->createSubjectNode( id: 'sTestSLL2222223', name: 'Apple Tart', wikiId: $otherWikiId );

		$this->assertEquals(
			[ new SubjectLabelLookupResult( self::SUBJECT_ID_1, 'Apple Pie' ) ],
			$this->getSubjectLabelsMatching( 'Apple' )
		);
	}

	public function testExcludesSubjectsWithoutWikiId(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: self::SUBJECT_ID_1, label: new SubjectLabel( 'Apple Pie' ) ),
		) );

		$this->createSubjectNode( id: 'sTestSLL3333331', name: 'Apple Crumble', wikiId: null );
		$this->createSubjectNode( id: 'sTestSLL3333332', name: 'Apple Tart', wikiId: null );

		$this->assertEquals(
			[ new SubjectLabelLookupResult( self::SUBJECT_ID_1, 'Apple Pie' ) ],
			$this->getSubjectLabelsMatching( 'Apple' )
		);
	}

	public function testExcludesForeignSubjectsReachableThroughALocalPage(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: self::SUBJECT_ID_1, label: new SubjectLabel( 'Apple Pie' ) ),
		) );

		// A cross-wiki-shared Subject id (ADR 22) can leave a local Page holding a Subject stamped
		// for another wiki. The Subject filter must still withhold it so its foreign label, and a
		// page id that resolves against the wrong wiki, cannot surface.
		$this->getClient()->run(
			'CREATE (:Page { id: 500, wiki_id: $wikiId })-[:HasSubject { isMain: false }]->'
				. '(:Subject:' . Cypher::escape( TestSubject::DEFAULT_SCHEMA_ID )
				. ' { id: "sTestSLL4444441", name: "Apple Tart", wiki_id: $otherWikiId })',
			[ 'wikiId' => $this->currentWikiId(), 'otherWikiId' => $this->currentWikiId() . '-other' ]
		);

		$this->assertEquals(
			[ new SubjectLabelLookupResult( self::SUBJECT_ID_1, 'Apple Pie' ) ],
			$this->getSubjectLabelsMatching( 'Apple' )
		);
	}

	public function testReturnsALocallyOwnedSubjectOnceWhenAForeignPageAlsoReferencesIt(): void {
		$this->saveSubjectOnPage( pageId: 7, subjectId: self::SUBJECT_ID_1, label: 'Apple Pie' );

		// In a shared graph the same Subject node (ADR 22 keeps it by bare id) can be referenced by
		// a foreign wiki's Page too. Only the local owning Page is followed, so the label appears
		// once and the page id used for the read check resolves within this wiki, not the foreign
		// one whose colliding page id would gate an unrelated local page.
		$this->getClient()->run(
			'MATCH (subject:Subject { id: $subjectId }) '
				. 'CREATE (:Page { id: 7, wiki_id: $otherWikiId })-[:HasSubject { isMain: false }]->(subject)',
			[ 'subjectId' => self::SUBJECT_ID_1, 'otherWikiId' => $this->currentWikiId() . '-other' ]
		);

		$this->assertEquals(
			[ new SubjectLabelLookupResult( self::SUBJECT_ID_1, 'Apple Pie' ) ],
			$this->getSubjectLabelsMatching( 'Apple' )
		);
	}

	public function testSubjectsOnUnreadablePagesAreOmitted(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: self::SUBJECT_ID_1, label: new SubjectLabel( 'Apple Pie' ) ),
		) );

		$this->assertSame(
			[],
			$this->newLookup( readAuthorizer: new StubPageReadAuthorizer( allowed: false ) )
				->getSubjectLabelsMatching( 'Apple', 10, TestSubject::DEFAULT_SCHEMA_ID )
		);
	}

	public function testOverFetchesPastUnreadableRowsToFillTheLimit(): void {
		$this->saveSubjectOnPage( pageId: 1, subjectId: 'sTestSLL1111141', label: 'Apple 1' );
		$this->saveSubjectOnPage( pageId: 2, subjectId: 'sTestSLL1111142', label: 'Apple 2' );
		$this->saveSubjectOnPage( pageId: 3, subjectId: 'sTestSLL1111143', label: 'Apple 3' );

		// Page 2 is hidden and sorts between the two readable rows, so a naive "LIMIT 2 then
		// filter" would return only Apple 1. Over-fetching then re-limiting must still yield two.
		$results = $this->newLookup( readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] ) )
			->getSubjectLabelsMatching( 'Apple', 2, TestSubject::DEFAULT_SCHEMA_ID );

		$this->assertEquals(
			[
				new SubjectLabelLookupResult( 'sTestSLL1111141', 'Apple 1' ),
				new SubjectLabelLookupResult( 'sTestSLL1111143', 'Apple 3' ),
			],
			$results
		);
	}

	private function saveSubjects( SubjectMap $subjects ): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: 1,
			properties: TestPageProperties::build( title: 'Foo' ),
			childSubjects: $subjects
		) );
	}

	private function saveSubjectOnPage( int $pageId, string $subjectId, string $label ): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: $pageId,
			properties: TestPageProperties::build( title: 'Page ' . $pageId ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: $subjectId, label: new SubjectLabel( $label ) )
			)
		) );
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID ),
				TestSchema::build( name: 'Recipe' ),
				TestSchema::build( name: 'Plant' ),
				TestSchema::build( name: 'Company' ),
			)
		);
	}

	private function getSubjectLabelsMatching( string $search, int $limit = 10 ): array {
		return $this->newLookup()->getSubjectLabelsMatching( $search, $limit, TestSubject::DEFAULT_SCHEMA_ID );
	}

	private function newLookup(
		ClientInterface $client = null,
		?PageReadAuthorizer $readAuthorizer = null
	): Neo4jSubjectLabelLookup {
		return new Neo4jSubjectLabelLookup(
			client: $client ?? $this->getClient(),
			wikiId: $this->currentWikiId(),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
		);
	}

	private function currentWikiId(): string {
		return NeoWikiExtension::getInstance()->config->wikiId;
	}

	/**
	 * Creates a Subject node directly in the graph, bypassing the projection store, so a test can
	 * plant a node with a chosen wiki_id (or none at all, as written before wiki_id stamping existed).
	 *
	 * The node is attached to a Page carrying the same wiki_id via a HasSubject edge, so it is
	 * reachable by the lookup's Page->Subject traversal. Without the Page it would be dropped for
	 * having no Page at all, which would make the wiki filter these tests target untested.
	 */
	private function createSubjectNode( string $id, string $name, ?string $wikiId ): void {
		$subjectProperties = [ 'id' => $id, 'name' => $name ];
		$pageProperties = [ 'id' => 0 ];

		if ( $wikiId !== null ) {
			$subjectProperties['wiki_id'] = $wikiId;
			$pageProperties['wiki_id'] = $wikiId;
		}

		$this->getClient()->run(
			'CREATE (:Page $pageProperties)-[:HasSubject { isMain: false }]->'
				. '(:Subject:' . Cypher::escape( TestSubject::DEFAULT_SCHEMA_ID ) . ' $subjectProperties)',
			[ 'pageProperties' => $pageProperties, 'subjectProperties' => $subjectProperties ]
		);
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}
}
