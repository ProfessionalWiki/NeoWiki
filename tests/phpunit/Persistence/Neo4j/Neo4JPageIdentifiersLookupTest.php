<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4JPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4JPageIdentifiersLookup
 */
class Neo4JPageIdentifiersLookupTest extends NeoWikiIntegrationTestCase {

	private const string GUID_1 = 'sTestNPL1111111';
	private const string GUID_2 = 'sTestNPL1111112';
	private const string GUID_3 = 'sTestNPL1111113';
	private const string GUID_4 = 'sTestNPL1111114';
	private const string GUID_5 = 'sTestNPL1111115';
	private const string GUID_404 = 'sTestNPL111nope';

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReturnsNullOnEmptyGraph(): void {
		$this->assertNull( $this->newLookup()->getPageIdOfSubject( new SubjectId( self::GUID_404 ) ) );
	}

	private function newLookup( ClientInterface $client = null ): Neo4JPageIdentifiersLookup {
		return new Neo4JPageIdentifiersLookup(
			client: $client ?? $this->getClient()
		);
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}

	public function testFindsIdOfPage(): void {
		$queryStore = NeoWikiExtension::getInstance()->newNeo4jQueryStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: TestSubject::DEFAULT_SCHEMA_ID )
			)
		);

		$queryStore->savePage( TestPage::build(
			id: 1,
			properties: TestPageProperties::build( title: 'Foo' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_4 ),
			)
		) );

		$queryStore->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( title: 'Bar' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_1 ),
				TestSubject::build( id: self::GUID_2 ), // Target
				TestSubject::build( id: self::GUID_3 ),
			)
		) );

		$queryStore->savePage( TestPage::build(
			id: 32202,
			properties: TestPageProperties::build( title: 'Baz' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_5 ),
			)
		) );

		$this->assertEquals(
			new PageIdentifiers( new PageId( 42 ), 'Bar' ),
			$this->newLookup( $this->getClient() )->getPageIdOfSubject( new SubjectId( self::GUID_2 ) )
		);
	}

}
