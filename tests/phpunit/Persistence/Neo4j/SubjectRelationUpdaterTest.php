<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki\Persistence\Neo4j;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\SubjectRelationUpdater;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\MediaWiki\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j\SubjectRelationUpdater
 * @group Database
 */
class SubjectRelationUpdaterTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 'sTestSRU1111111';
	private const string TARGET_SUBJECT_1 = 'sTestSRU1111112';
	private const string TARGET_SUBJECT_2 = 'sTestSRU1111113';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSubjects();
	}

	private function createSubjects(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->createPageWithSubjects(
			pageName: 'SubjectRelationUpdaterTest',
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
		$updater = new SubjectRelationUpdater(
			new SubjectId( self::SUBJECT_ID ),
			$relations,
			NeoWikiExtension::getInstance()->getNeo4jClient()
		);
		$updater->updateRelations();
	}

	private function assertHasRelations( TypedRelationList $expected ): void {
		$result = NeoWikiExtension::getInstance()->getNeo4jClient()->run(
			'MATCH (subject {id: $subjectId})-[relation]->(target)
       		RETURN relation, target.id as targetId
       		ORDER BY relation.id',
			[ 'subjectId' => self::SUBJECT_ID ]
		);

		$this->assertEquals(
			$this->buildExpectedRelations( $expected ),
			$this->buildActualRelations( $result )
		);
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

	private function buildActualRelations( SummarizedResult $result ): array {
		$actualRelations = [];

		foreach ( $result->getResults()->toRecursiveArray() as $row ) {
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

	public function testUpdatesRelationTypes(): void {
		$this->updateRelations(
			new TypedRelationList( [
				TestRelation::build(
					id: 'rTestSRU1111rr1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				)->withType( new RelationType( 'Type1v2' ) ),
				TestRelation::build(
					id: 'rTestSRU1111rr2',
					targetId: self::TARGET_SUBJECT_2,
					properties: new RelationProperties( [ 'hello' => 'there' ] ),
				)->withType( new RelationType( 'Type2v2' ) ),
			] )
		);

		$expectedRelations = new TypedRelationList( [
			TestRelation::build(
				id: 'rTestSRU1111rr1',
				targetId: self::TARGET_SUBJECT_1,
				properties: new RelationProperties( [ 'foo' => 'bar', 'new' => 1337 ] ),
			)->withType( new RelationType( 'Type1v2' ) ),
			TestRelation::build(
				id: 'rTestSRU1111rr2',
				targetId: self::TARGET_SUBJECT_2,
				properties: new RelationProperties( [ 'hello' => 'there' ] ),
			)->withType( new RelationType( 'Type2v2' ) ),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
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

}
