<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageProperties;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4JPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4JPageIdentifiersLookup
 */
class Neo4jPageIdLookupTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReturnsNullOnEmptyGraph(): void {
		$this->assertNull( $this->newLookup()->getPageIdOfSubject( new SubjectId( '404' ) ) );
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
		$client = $this->getClient();
		$queryStore = new Neo4jQueryStore( $client );

		$queryStore->savePage( TestPage::build(
			id: 1,
			properties: new PageProperties( title: 'Foo' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-4' ),
			)
		) );

		$queryStore->savePage( TestPage::build(
			id: 42,
			properties: new PageProperties( title: 'Bar' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-1' ),
				TestSubject::build( id: 'GUID-2' ), // Target
				TestSubject::build( id: 'GUID-3' ),
			)
		) );

		$queryStore->savePage( TestPage::build(
			id: 32202,
			properties: new PageProperties( title: 'Baz' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-5' ),
			)
		) );

		$this->assertEquals(
			new PageIdentifiers( new PageId( 42 ), 'Bar' ),
			$this->newLookup( $client )->getPageIdOfSubject( new SubjectId( 'GUID-2' ) )
		);
	}

}
