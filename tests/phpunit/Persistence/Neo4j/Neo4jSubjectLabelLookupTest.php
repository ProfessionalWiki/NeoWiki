<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Application\SubjectSearchResult;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jSubjectLabelLookup
 */
class Neo4jSubjectLabelLookupTest extends NeoWikiIntegrationTestCase {

	private const string SUBJECT_ID_1 = 'sTestSLL1111111';
	private const string SUBJECT_ID_2 = 'sTestSLL1111112';

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReturnsEmptyArrayOnEmptyGraph(): void {
		$this->assertSame( [], $this->newLookup()->getSubjectLabelsMatching( 'foo' ) );
	}

	public function testFindsSubjectsMatchingPrefix(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build( id: self::SUBJECT_ID_1, label: new SubjectLabel( 'Apple Pie' ) ),
			TestSubject::build( id: self::SUBJECT_ID_2, label: new SubjectLabel( 'Apple Crumble' ) ),
		) );

		$results = $this->newLookup()->getSubjectLabelsMatching( 'Apple' );

		$this->assertCount( 2, $results );
		$this->assertContainsEquals(
			new SubjectSearchResult( self::SUBJECT_ID_1, 'Apple Pie' ),
			$results
		);
		$this->assertContainsEquals(
			new SubjectSearchResult( self::SUBJECT_ID_2, 'Apple Crumble' ),
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

		$this->assertSame( [], $this->newLookup()->getSubjectLabelsMatching( 'Apple' ) );
	}

	public function testCaseInsensitiveSearch(): void {
		$this->saveSubjects( new SubjectMap(
			TestSubject::build(
				id: self::SUBJECT_ID_1,
				label: new SubjectLabel( 'Apple' )
			)
		) );

		$results = $this->newLookup()->getSubjectLabelsMatching( 'apple' );
		$this->assertCount( 1, $results );
		$this->assertEquals( 'Apple', $results[0]->label );
	}

	private function saveSubjects( SubjectMap $subjects ): void {
		$this->newQueryStore()->savePage( TestPage::build(
			id: 1,
			properties: TestPageProperties::build( title: 'Foo' ),
			childSubjects: $subjects
		) );
	}

	private function newQueryStore(): Neo4jQueryStore {
		return NeoWikiExtension::getInstance()->newNeo4jQueryStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID )
			)
		);
	}

	private function newLookup( ClientInterface $client = null ): Neo4jSubjectLabelLookup {
		return new Neo4jSubjectLabelLookup(
			client: $client ?? $this->getClient()
		);
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}
}
