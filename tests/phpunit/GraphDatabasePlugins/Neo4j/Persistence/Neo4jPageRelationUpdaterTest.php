<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
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
			otherSubjects: new SubjectMap(
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
			)->withPropertyName( new PropertyName( 'Type1' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::TARGET_SUBJECT_2,
			)->withPropertyName( new PropertyName( 'Type2' ) ),
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
				'type' => $relation->propertyName->text,
				'properties' => [ 'id' => $relation->id->asString() ],
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
				)->withPropertyName( new PropertyName( 'Type1' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr2',
					targetId: self::TARGET_SUBJECT_2,
				)->withPropertyName( new PropertyName( 'Type2' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr3',
					targetId: self::TARGET_SUBJECT_2,
				)->withPropertyName( new PropertyName( 'Type2' ) ),
			] )
		);

		$expectedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::TARGET_SUBJECT_2,
			)->withPropertyName( new PropertyName( 'Type2' ) ),
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
		)->withPropertyName( new PropertyName( 'Type2' ) );

		$this->updateRelations( new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
			)->withPropertyName( new PropertyName( 'Type1' ) ),
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
				)->withPropertyName( new PropertyName( 'Type1' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr2',
					targetId: self::TARGET_SUBJECT_2,
				)->withPropertyName( new PropertyName( 'Type2' ) ),
			] )
		);

		$expectedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_2,
			)->withPropertyName( new PropertyName( 'Type1' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::SUBJECT_ID,
			)->withPropertyName( new PropertyName( 'Type2' ) ),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	public function testChangingARelationTypeReplacesItsEdge(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
				)->withPropertyName( new PropertyName( 'Type1' ) ),
			] )
		);

		$retypedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
			)->withPropertyName( new PropertyName( 'Type1v2' ) ),
		] );

		$this->updateRelations( $retypedRelations );

		$this->assertHasRelations( $retypedRelations );
	}

	public function testRelationTypeIsStoredVerbatim(): void {
		$relations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
			)->withPropertyName( new PropertyName( 'Made by' . str_repeat( chr( 92 ) . 'u0060', 2 ) ) ),
		] );

		$this->updateRelations( $relations );

		$this->assertHasRelations( $relations );
	}

	public function testRelationWithNonExistentTargetNodeDoesNotCreateDuplicateSubject(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: 'sTestSRU111nope',
				)->withPropertyName( new PropertyName( 'Owner' ) )
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
				)->withPropertyName( new PropertyName( 'Owner' ) )
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
