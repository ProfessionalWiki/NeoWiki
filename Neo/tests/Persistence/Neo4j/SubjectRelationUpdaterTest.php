<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\SubjectRelationUpdater;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\SubjectRelationUpdater
 * @group Database
 */
class SubjectRelationUpdaterTest extends NeoWikiIntegrationTestCase {

	private const SUBJECT_ID = '00000000-1237-0000-0000-000000000005';
	private const TARGET_SUBJECT_1 = '00000000-0000-0000-0001-000000000000';
	private const TARGET_SUBJECT_2 = '00000000-0000-0000-0002-000000000000';

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
		$relations = new RelationList( [
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000001',
				type: 'Type1',
				targetId: self::TARGET_SUBJECT_1,
				properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
			),
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000002',
				type: 'Type2',
				targetId: self::TARGET_SUBJECT_2,
			),
		] );

		$this->updateRelations( $relations );

		$this->assertHasRelations( $relations );
	}

	private function updateRelations( RelationList $relations ): void {
		$updater = new SubjectRelationUpdater(
			new SubjectId( self::SUBJECT_ID ),
			$relations,
			NeoWikiExtension::getInstance()->getNeo4jClient()
		);
		$updater->updateRelations();
	}

	private function assertHasRelations( RelationList $expected ): void {
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

	private function buildExpectedRelations( RelationList $expected ): array {
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
			new RelationList( [
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000001',
					type: 'Type1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				),
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000002',
					type: 'Type2',
					targetId: self::TARGET_SUBJECT_2,
				),
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000003',
					type: 'Type2',
					targetId: self::TARGET_SUBJECT_2,
				),
			] )
		);

		$expectedRelations = new RelationList( [
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000002',
				type: 'Type2',
				targetId: self::TARGET_SUBJECT_2,
			),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	public function testUpdatesRelationProperties(): void {
		$this->updateRelations(
			new RelationList( [
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000001',
					type: 'Type1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				),
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000002',
					type: 'Type2',
					targetId: self::TARGET_SUBJECT_2,
					properties: new RelationProperties( [ 'hello' => 'there' ] ),
				),
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000003',
					type: 'Type2',
					targetId: self::TARGET_SUBJECT_2,
				),
			] )
		);

		$expectedRelations = new RelationList( [
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000001',
				type: 'Type1',
				targetId: self::TARGET_SUBJECT_1,
				properties: new RelationProperties( [ 'bah' => 1337, 'foo' => 'bar' ] ),
			),
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000002',
				type: 'Type2',
				targetId: self::TARGET_SUBJECT_2,
			),
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000004',
				type: 'Type2',
				targetId: self::TARGET_SUBJECT_2,
				properties: new RelationProperties( [ 'neo' => 'wiki' ] ),
			),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	public function testUpdatesRelationTargets(): void {
		$this->updateRelations(
			new RelationList( [
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000001',
					type: 'Type1',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				),
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000002',
					type: 'Type2',
					targetId: self::TARGET_SUBJECT_2,
					properties: new RelationProperties( [ 'hello' => 'there' ] ),
				),
			] )
		);

		$expectedRelations = new RelationList( [
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000001',
				type: 'Type1',
				targetId: self::TARGET_SUBJECT_2,
				properties: new RelationProperties( [ 'foo' => 'bar', 'new' => 1337 ] ),
			),
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000002',
				type: 'Type2',
				targetId: self::SUBJECT_ID,
				properties: new RelationProperties( [ 'hello' => 'there' ] ),
			),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

	public function testUpdatesRelationTypes(): void {
		$this->updateRelations(
			new RelationList( [
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000001',
					type: 'Type1v2',
					targetId: self::TARGET_SUBJECT_1,
					properties: new RelationProperties( [ 'foo' => 'bar', 'baz' => 42 ] ),
				),
				TestRelation::build(
					id: '00000000-1237-0000-0000-000000000002',
					type: 'Type2v2',
					targetId: self::TARGET_SUBJECT_2,
					properties: new RelationProperties( [ 'hello' => 'there' ] ),
				),
			] )
		);

		$expectedRelations = new RelationList( [
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000001',
				type: 'Type1v2',
				targetId: self::TARGET_SUBJECT_1,
				properties: new RelationProperties( [ 'foo' => 'bar', 'new' => 1337 ] ),
			),
			TestRelation::build(
				id: '00000000-1237-0000-0000-000000000002',
				type: 'Type2v2',
				targetId: self::TARGET_SUBJECT_2,
				properties: new RelationProperties( [ 'hello' => 'there' ] ),
			),
		] );

		$this->updateRelations( $expectedRelations );

		$this->assertHasRelations( $expectedRelations );
	}

}
