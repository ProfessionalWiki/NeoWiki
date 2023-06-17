<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Types\CypherMap;
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

	/**
	 * TODO Tests:
	 * Add relations
	 * Remove relations
	 * Update relation properties
	 * Update relation target
	 * Update relation type
	 */
	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testCreatesRelations(): void {
		$this->createSubjects();

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

		$updater = new SubjectRelationUpdater(
			new SubjectId( self::SUBJECT_ID ),
			$relations,
			NeoWikiExtension::getInstance()->getNeo4jClient()
		);
		$updater->updateRelations();

		$this->assertHasRelations( $relations );
	}

	private function createSubjects(): void {
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->createPageWithSubjects(
			pageName: 'SubjectRelationUpdaterTest',
			mainSubject: TestSubject::build( id: self::SUBJECT_ID ), // To hold the relations
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::TARGET_SUBJECT_1 ), // To be a relation target
				TestSubject::build( id: self::TARGET_SUBJECT_2 ),
			)
		);
	}

	private function assertHasRelations( RelationList $expected ): void {
		$result = NeoWikiExtension::getInstance()->getNeo4jClient()->run(
			'MATCH (subject {id: $subjectId})-[relation]->(target)
       		RETURN relation, target.id as targetId
       		ORDER BY relation.id',
			[ 'subjectId' => self::SUBJECT_ID ]
		)->getResults()->toRecursiveArray();

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

		$actualRelations = [];
		foreach ( $result as $row ) {
			$actualRelations[$row['relation']['properties']['id']] = [
				'targetId' => $row['targetId'],
				'type' => $row['relation']['type'],
				'properties' => $row['relation']['properties']->toArray(),
			];
		}

		$this->assertEquals( $expectedRelations, $actualRelations );
	}

}
