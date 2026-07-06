<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Exception\Neo4jException;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jConstraintUpdater;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jConstraintUpdater
 * @group Database
 */
class Neo4jConstraintUpdaterTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID = 'sTestNCU1111111';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testDefaultConstraintsAreCreated(): void {
		$updater = $this->newConstraintUpdater();

		$updater->createDefaultConstraints();

		$result = $this->readGraph(
			'SHOW CONSTRAINTS YIELD name, type, entityType, labelsOrTypes, properties ORDER BY name'
		);

		$this->assertSame(
			[
				[
					'name' => 'Page wiki_id id',
					'type' => 'NODE_PROPERTY_UNIQUENESS',
					'entityType' => 'NODE',
					'labelsOrTypes' => [ 'Page' ],
					'properties' => [ 'wiki_id', 'id' ],
				],
				[
					'name' => 'Subject id',
					'type' => 'NODE_PROPERTY_UNIQUENESS',
					'entityType' => 'NODE',
					'labelsOrTypes' => [ 'Subject' ],
					'properties' => [ 'id' ],
				]
			],
			$result->toRecursiveArray()
		);
	}

	private function newConstraintUpdater(): Neo4jConstraintUpdater {
		return new Neo4jConstraintUpdater(
			NeoWikiExtension::getInstance()->getWriteQueryEngine()
		);
	}

	public function testPageWithDuplicateIdInSameWikiCannotBeCreated(): void {
		$this->newConstraintUpdater()->createDefaultConstraints();

		$store = $this->newProjectionStore();
		$wikiId = NeoWikiExtension::getInstance()->config->wikiId;

		$store->savePage( TestPage::build( id: 42 ) );

		$this->expectException( Neo4jException::class );
		$this->expectExceptionMessageMatches(
			'/Neo.ClientError.Schema.ConstraintValidationFailed.*already exists with label `Page`/'
		);

		$this->writeGraph(
			'CREATE (:Page {name: "Test", id: 42, wiki_id: "' . $wikiId . '"} )'
		);
	}

	public function testPagesWithSameIdInDifferentWikisCanCoexist(): void {
		$this->newConstraintUpdater()->createDefaultConstraints();

		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build( id: 42 ) );

		// A page with the same id but a different wiki_id must not violate the composite constraint.
		$this->writeGraph(
			'CREATE (:Page {name: "Test", id: 42, wiki_id: "some_other_wiki"} )'
		);

		$result = $this->readGraph( 'MATCH (page:Page {id: 42}) RETURN count(page) AS count' );

		$this->assertSame( 2, $result->first()->toRecursiveArray()['count'] );
	}

	public function testSubjectWithDuplicateIdCannotBeCreated(): void {
		$this->newConstraintUpdater()->createDefaultConstraints();

		$store = $this->newProjectionStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::SUBJECT_ID )
		) );

		$this->expectException( Neo4jException::class );
		$this->expectExceptionMessageMatches(
			'/Neo.ClientError.Schema.ConstraintValidationFailed.*already exists with label `Subject` and property `id` = \'' . self::SUBJECT_ID . '\'"/'
		);

		$this->writeGraph(
			'CREATE (:Subject {name: "Test", id: "' . self::SUBJECT_ID . '"} )'
		);
	}

}
