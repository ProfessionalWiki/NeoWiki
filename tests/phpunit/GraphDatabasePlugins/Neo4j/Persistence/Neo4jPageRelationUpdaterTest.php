<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jOrphanCandidates;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jPageRelationUpdater;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Drives the relation reconciliation for a single Subject. The multi-Subject batching it exists for
 * is covered by Neo4jProjectionStoreTest, which saves whole pages.
 *
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jPageRelationUpdater
 * @group Database
 */
class Neo4jPageRelationUpdaterTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 'sTestSRU1111111';
	private const string TARGET_SUBJECT_1 = 'sTestSRU1111112';
	private const string TARGET_SUBJECT_2 = 'sTestSRU1111113';
	private const string WIKI_ID = 'test_wiki';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSubjects();
	}

	private function createSubjects(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->createPageWithSubjects(
			pageName: 'PageRelationUpdaterTest',
			mainSubject: TestSubject::build( id: self::SUBJECT_ID, label: 'Relation holder' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::TARGET_SUBJECT_1, label: 'Target 1' ),
				TestSubject::build( id: self::TARGET_SUBJECT_2, label: 'Target 2' ),
			)
		);
	}

	public function testCreatesRelations(): void {
		$relations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
				properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
			)->withType( new RelationType( 'Type1' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::TARGET_SUBJECT_2,
			)->withType( new RelationType( 'Type2' ) ),
		] );

		$this->updateRelations( $relations );

		$this->assertHasRelations( $relations );
	}

	private function updateRelations( TypedRelationList $relations ): void {
		( new Neo4jPageRelationUpdater(
			NeoWikiExtension::getInstance()->getNeo4jClient(),
			self::WIKI_ID,
			new Neo4jOrphanCandidates()
		) )->updateRelations( [ self::SUBJECT_ID => $relations ] );
	}

	private function assertHasRelations( TypedRelationList $expected ): void {
		$rows = NeoWikiExtension::getInstance()->getNeo4jClient()->run(
			'MATCH (subject {id: $subjectId})-[relation]->(target)
       		RETURN relation, target.id as targetId
       		ORDER BY relation.id',
			[ 'subjectId' => self::SUBJECT_ID ]
		)->getResults()->toRecursiveArray();

		$this->assertEquals(
			$this->buildExpectedRelations( $expected ),
			$this->buildActualRelations( $rows )
		);

		// Both maps are keyed by relation id, so a second edge carrying an id the subject already
		// holds is invisible in them. Count the edges themselves as well.
		$this->assertSameSize( $expected->relations, $rows );
	}

	private function buildExpectedRelations( TypedRelationList $expected ): array {
		$expectedRelations = [];

		foreach ( $expected->relations as $relation ) {
			$expectedRelations[$relation->id->asString()] = [
				'targetId' => $relation->targetId->text,
				'type' => $relation->type->getText(),
				'properties' => array_merge(
					$relation->properties->map,
					[ 'id' => $relation->id->asString() ]
				),
			];
		}

		return $expectedRelations;
	}

	private function buildActualRelations( array $rows ): array {
		$actualRelations = [];

		foreach ( $rows as $row ) {
			$actualRelations[$row['relation']['properties']['id']] = [
				'targetId' => $row['targetId'],
				'type' => $row['relation']['type'],
				'properties' => $row['relation']['properties']->toArray(),
			];
		}

		return $actualRelations;
	}

	public function testRemovesRelations(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				)->withType( new RelationType( 'Type1' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr2',
					targetId: self::TARGET_SUBJECT_2,
				)->withType( new RelationType( 'Type2' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr3',
					targetId: self::TARGET_SUBJECT_2,
				)->withType( new RelationType( 'Type2' ) ),
			] )
		);

		$expectedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::TARGET_SUBJECT_2,
			)->withType( new RelationType( 'Type2' ) ),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	public function testUpdatesRelationProperties(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				)->withType( new RelationType( 'Type1' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr2',
					targetId: self::TARGET_SUBJECT_2,
					properties: new RelationProperties( [ 'hello' => 'there' ] ),
				)->withType( new RelationType( 'Type2' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr3',
					targetId: self::TARGET_SUBJECT_2,
				)->withType( new RelationType( 'Type2' ) ),
			] )
		);

		$expectedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
				properties: new RelationProperties( [ 'bah' => 1337, 'foo' => 'bar' ] ),
			)->withType( new RelationType( 'Type1' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::TARGET_SUBJECT_2,
			)->withType( new RelationType( 'Type2' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr4',
				targetId: self::TARGET_SUBJECT_2,
				properties: new RelationProperties( [ 'neo' => 'wiki' ] ),
			)->withType( new RelationType( 'Type2' ) ),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	/**
	 * A relation id addresses one edge of one subject, so the last relation holding a repeated id wins.
	 */
	public function testRepeatedRelationIdLeavesASingleEdge(): void {
		$lastRelationWithTheId = TestRelation::build(
			id: 'rTestSRU1111rr1',
			targetId: self::TARGET_SUBJECT_2,
		)->withType( new RelationType( 'Type2' ) );

		$this->updateRelations( new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
			)->withType( new RelationType( 'Type1' ) ),
			$lastRelationWithTheId,
		] ) );

		$this->assertHasRelations( new TypedRelationList( [ $lastRelationWithTheId ] ) );
	}

	public function testUpdatesRelationTargets(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				)->withType( new RelationType( 'Type1' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr2',
					targetId: self::TARGET_SUBJECT_2,
					properties: new RelationProperties( [ 'hello' => 'there' ] ),
				)->withType( new RelationType( 'Type2' ) ),
			] )
		);

		$expectedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_2,
				properties: new RelationProperties( [ 'foo' => 'bar', 'new' => 1337 ] ),
			)->withType( new RelationType( 'Type1' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::SUBJECT_ID,
				properties: new RelationProperties( [ 'hello' => 'there' ] ),
			)->withType( new RelationType( 'Type2' ) ),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	/**
	 * Changing a relation's type does not replace its edge. The removal pass compares the requested
	 * type against a `type` property that the projection itself never writes, so the comparison is
	 * null and the old edge survives; the upsert then adds the new edge alongside it, both carrying
	 * the same relation id. Asserted as it is rather than as it should be.
	 *
	 * @see https://github.com/ProfessionalWiki/NeoWiki/issues/1135 Fixing that flips this expectation.
	 */
	public function testChangingARelationTypeAddsTheNewEdgeAndKeepsTheOld(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar' ] ),
				)->withType( new RelationType( 'Type1' ) ),
			] )
		);

		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar' ] ),
				)->withType( new RelationType( 'Type1v2' ) ),
			] )
		);

		$this->assertSame( [ 'Type1', 'Type1v2' ], $this->relationTypesOf( 'rTestSRU1111rr1' ) );
	}

	/**
	 * @return string[]
	 */
	private function relationTypesOf( string $relationId ): array {
		$rows = NeoWikiExtension::getInstance()->getNeo4jClient()->run(
			'MATCH ({id: $subjectId})-[relation {id: $relationId}]->()
       		RETURN type(relation) AS type
       		ORDER BY type',
			[ 'subjectId' => self::SUBJECT_ID, 'relationId' => $relationId ]
		)->getResults()->toRecursiveArray();

		return array_column( $rows, 'type' );
	}

	public function testRelationWithNonExistentTargetNodeDoesNotCreateDuplicateSubject(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: 'sTestSRU111nope',
					properties: new RelationProperties( [] ),
				)->withType( new RelationType( 'RelationType' ) )
			] )
		);

		$result = NeoWikiExtension::getInstance()->getNeo4jClient()->run(
			'MATCH (subject {id: $subjectId})
       		RETURN subject',
			[ 'subjectId' => self::SUBJECT_ID ]
		);

		$this->assertCount( 1, $result->getResults()->toRecursiveArray() );
	}

	public function testRelationToNonExistentTargetCreatesStubTarget(): void {
		$targetId = 'sTestSRU111nope';

		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: $targetId,
					properties: new RelationProperties( [] ),
				)->withType( new RelationType( 'RelationType' ) )
			] )
		);

		$result = NeoWikiExtension::getInstance()->getNeo4jClient()->run(
			'MATCH (target {id: $targetId})
				RETURN labels(target) AS labels, target.id AS id, target.wiki_id AS wikiId, size(keys(target)) AS propertyCount',
			[ 'targetId' => $targetId ]
		);

		// Counted rather than read through first(), which would not see a second node under the same id.
		$this->assertCount( 1, $result->toArray(), 'The relation should create a single stub target' );

		$row = $result->first()->toRecursiveArray();

		$this->assertSame( [ 'Subject' ], $row['labels'] );
		$this->assertSame( $targetId, $row['id'] );
		$this->assertSame( self::WIKI_ID, $row['wikiId'] );
		$this->assertSame( 2, $row['propertyCount'], 'Stub target should keep only the id and wiki_id properties' );
	}

}
