<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\Neo4j;

use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\Neo4jQueryStore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

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

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'TestPage'
			)
		) );

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
	}

	public function testSavesPageSubjects(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: 'GUID-1' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-2' ),
				TestSubject::build( id: 'GUID-3' ),
			)
		) );

		$this->assertPageHasSubjects(
			[
				[ 'id' => 'GUID-1', 'hs' => [ 'isMain' => true ] ],
				[ 'id' => 'GUID-2', 'hs' => [ 'isMain' => false ] ],
				[ 'id' => 'GUID-3', 'hs' => [ 'isMain' => false ] ]
			],
			42,
			$store
		);
	}

	private function assertPageHasSubjects( array $expectedSubjects, int $pageId, Neo4jQueryStore $store ): void {
		$result = $store->runReadQuery(
			'
			MATCH (page:Page {id: ' . $pageId . '})-[hs:HasSubject]->(subject)
			RETURN subject.id as id, properties(hs) as hs
			ORDER BY id'
		)->getResults()->toRecursiveArray();

		$this->assertSame( $expectedSubjects, $result );
	}

	public function testSavesPageRemovesObsoleteSubjects(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			mainSubject: TestSubject::build( id: 'GUID-1' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-2' ),
				TestSubject::build( id: 'GUID-3' ),
			)
		) );

		$store->savePage( TestPage::build(
			id: 42,
			childSubjects: new SubjectMap(
				TestSubject::build( id: 'GUID-2' ),
				TestSubject::build( id: 'GUID-4' ),
			)
		) );

		$this->assertPageHasSubjects(
			[
				[ 'id' => 'GUID-2', 'hs' => [ 'isMain' => false ] ],
				[ 'id' => 'GUID-4', 'hs' => [ 'isMain' => false ] ]
			],
			42,
			$store
		);
	}

	public function testSavingPageAndThenDeletingItLeavesNoTrace(): void {
		$store = $this->newQueryStore();

		$store->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build(
				title: 'TestPage'
			)
		) );

		$store->deletePage( new PageId( 42 ) );

		$result = $store->runReadQuery( 'MATCH (n) RETURN *' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

}
