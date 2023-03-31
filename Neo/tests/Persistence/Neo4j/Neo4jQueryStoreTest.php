<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\TestPageInfo;
use ProfessionalWiki\NeoWiki\Tests\TestSubject;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore
 */
class Neo4jQueryStoreTest extends TestCase {

	public function testReadQueryReturnsNothingWhenDbIsEmpty(): void {
		$result = $this->newQueryStore()->runReadQuery( 'MATCH (n) RETURN n' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

	private function newQueryStore(): Neo4jQueryStore {
		try {
			$client = NeoWikiExtension::getInstance()->getNeo4jClient();
			$client->run( 'MATCH (n) DETACH DELETE n' );
		}
		catch ( \Exception $e ) {
			$this->markTestSkipped( 'Neo4j not available' );
		}

		return new Neo4jQueryStore( $client );
	}

	public function testSavesPageIdAndTitle(): void {
		$store = $this->newQueryStore();

		$store->savePage(
			pageId: 42,
			pageInfo: TestPageInfo::build(
				title: 'TestPage'
			),
			subjects: new SubjectMap()
		);

		$result = $store->runReadQuery( 'MATCH (page:Page {id: 42}) RETURN properties(page) as page' );

		/**
		 * @var CypherMap $first
		 */
		$first = $result->first();
		$page = $first->toRecursiveArray()['page'];

		$this->assertSame(
			42,
			$page['id']
		);

		$this->assertSame(
			'TestPage',
			$page['name']
		);

//		$this->assertSame( 1, $result->getSummary()->getCounters()->nodesCreated() );
	}

	public function testSavesPageSubjects(): void {
		$store = $this->newQueryStore();

		$store->savePage(
			pageId: 42,
			pageInfo: TestPageInfo::build(),
			subjects: new SubjectMap(
				TestSubject::build( id: 'GUID-1' ),
				TestSubject::build( id: 'GUID-2' ),
			)
		);

		$this->assertPageHasSubjects(
			[ [ 'id' => 'GUID-1' ], [ 'id' => 'GUID-2' ] ],
			42,
			$store
		);
	}

	private function assertPageHasSubjects( array $expectedSubjects, int $pageId, Neo4jQueryStore $store ): void {
		$result = $store->runReadQuery(
			'MATCH (page:Page {id: ' . $pageId . '})-[:HasSubject]->(subject) RETURN subject.id as id'
		)->getResults()->toRecursiveArray();

		foreach ( $expectedSubjects as $expectedSubject ) {
			$this->assertContains( $expectedSubject, $result );
		}
	}

	public function testSavesPageRemovesObsoleteSubjects(): void {
		$store = $this->newQueryStore();

		$store->savePage(
			pageId: 42,
			pageInfo: TestPageInfo::build(),
			subjects: new SubjectMap(
				TestSubject::build( id: 'GUID-1' ),
				TestSubject::build( id: 'GUID-2' ),
			)
		);

		$store->savePage(
			pageId: 42,
			pageInfo: TestPageInfo::build(),
			subjects: new SubjectMap(
				TestSubject::build( id: 'GUID-1' ),
				TestSubject::build( id: 'GUID-3' ),
			)
		);

		$this->assertPageHasSubjects(
			[ [ 'id' => 'GUID-1' ], [ 'id' => 'GUID-3' ] ],
			42,
			$store
		);
	}

	public function testGetPageIdForSubject(): void {
		$store = $this->newQueryStore();

		$store->savePage(
			pageId: 10,
			pageInfo: TestPageInfo::build(),
			subjects: new SubjectMap( TestSubject::build( id: 'GUID-1' ), )
		);
		$store->savePage(
			pageId: 20,
			pageInfo: TestPageInfo::build(),
			subjects: new SubjectMap( TestSubject::build( id: 'GUID-2' ), )
		);
		$store->savePage(
			pageId: 30,
			pageInfo: TestPageInfo::build(),
			subjects: new SubjectMap( TestSubject::build( id: 'GUID-3' ), )
		);

		$this->assertSame(
			20,
			$store->getPageIdForSubject( new SubjectId( 'GUID-2' ) )
		);
	}

}
