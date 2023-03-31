<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jPageIdLookup;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jPageIdLookup
 */
class Neo4jPageIdLookupTest extends TestCase {

	public function setUp(): void {
		try {
			$client = NeoWikiExtension::getInstance()->getNeo4jClient();
			$client->run( 'MATCH (n) DETACH DELETE n' );
		}
		catch ( \Exception $e ) {
			$this->markTestSkipped( 'Neo4j not available' );
		}
	}

	public function testReturnsNullOnEmptyGraph(): void {
		$this->assertNull( $this->newLookup()->getPageIdOfSubject( new SubjectId( '404' ) ) );
	}

	private function newLookup( ClientInterface $client = null ): Neo4jPageIdLookup {
		return new Neo4jPageIdLookup(
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
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-4' ),
			)
		) );

		$queryStore->savePage( TestPage::build(
			id: 42,
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-1' ),
				TestSubject::build( id: 'GUID-2' ), // Target
				TestSubject::build( id: 'GUID-3' ),
			)
		) );

		$queryStore->savePage( TestPage::build(
			id: 32202,
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-5' ),
			)
		) );

		$this->assertSame(
			42,
			$this->newLookup( $client )->getPageIdOfSubject( new SubjectId( 'GUID-2' ) )
		);
	}

}
