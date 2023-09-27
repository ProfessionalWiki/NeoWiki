<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Exception\Neo4jException;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jConstraintUpdater;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jConstraintUpdater
 */
class Neo4jConstraintUpdaterTest extends NeoWikiIntegrationTestCase {

	private const GUID_1 = '00000000-1237-0000-0000-000000000001';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testDefaultConstraintsAreCreated(): void {
		$updater = $this->newConstraintUpdater();
		$store = $this->newQueryStore();

		$updater->createDefaultConstraints();

		$result = $store->runReadQuery( 'SHOW CONSTRAINTS YIELD name, type, entityType, labelsOrTypes, properties' );

		$this->assertSame(
			[
				[
					'name' => 'Page id',
					'type' => 'UNIQUENESS',
					'entityType' => 'NODE',
					'labelsOrTypes' => [ 'Page' ],
					'properties' => [ 'id' ],
				],
				[
					'name' => 'Subject id',
					'type' => 'UNIQUENESS',
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

	private function newQueryStore(): Neo4jQueryStore {
		return NeoWikiExtension::getInstance()->newNeo4jQueryStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID )
			)
		);
	}

	public function testPageWithDuplicateIdCannotBeCreated(): void {
		$this->newConstraintUpdater()->createDefaultConstraints();

		$store = $this->newQueryStore();

		$store->savePage( TestPage::build( id: 42 ) );

		$this->expectException( Neo4jException::class );
		$this->expectExceptionMessageMatches(
			'/Neo.ClientError.Schema.ConstraintValidationFailed.*already exists with label `Page` and property `id` = 42"/'
		);

		$store->runWriteQuery(
			'CREATE (:Page {name: "Test", id: 42} )'
		);
	}

	public function testSubjectWithDuplicateIdCannotBeCreated(): void {
		$this->newConstraintUpdater()->createDefaultConstraints();

		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: self::GUID_1 )
		) );

		$this->expectException( Neo4jException::class );
		$this->expectExceptionMessageMatches(
			'/Neo.ClientError.Schema.ConstraintValidationFailed.*already exists with label `Subject` and property `id` = \'' . self::GUID_1 . '\'"/'
		);

		$store->runWriteQuery(
			'CREATE (:Subject {name: "Test", id: "' . self::GUID_1 . '"} )'
		);
	}

}
