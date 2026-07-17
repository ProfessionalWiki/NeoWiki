<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
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

	private function saveSubjects( SubjectMap $subjects ): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: 1,
			properties: TestPageProperties::build( title: 'Foo' ),
			childSubjects: $subjects
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

	private function newLookup( ClientInterface $client = null ): Neo4jSubjectLabelLookup {
		return new Neo4jSubjectLabelLookup(
			client: $client ?? $this->getClient(),
			wikiId: $this->currentWikiId(),
		);
	}

	private function currentWikiId(): string {
		return NeoWikiExtension::getInstance()->config->wikiId;
	}

	/**
	 * Creates a Subject node directly in the graph, bypassing the projection store, so a test can
	 * plant a node with a chosen wiki_id (or none at all, as written before wiki_id stamping existed).
	 */
	private function createSubjectNode( string $id, string $name, ?string $wikiId ): void {
		$properties = [ 'id' => $id, 'name' => $name ];

		if ( $wikiId !== null ) {
			$properties['wiki_id'] = $wikiId;
		}

		$this->getClient()->run(
			'CREATE (n:Subject:' . Cypher::escape( TestSubject::DEFAULT_SCHEMA_ID ) . ' $properties)',
			[ 'properties' => $properties ]
		);
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}
}
