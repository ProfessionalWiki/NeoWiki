<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jPageIdentifiersLookup
 */
class Neo4jPageIdentifiersLookupTest extends NeoWikiIntegrationTestCase {

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

	private function newLookup( ?ClientInterface $client = null ): Neo4jPageIdentifiersLookup {
		return new Neo4jPageIdentifiersLookup(
			client: $client ?? $this->getClient()
		);
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}

	public function testFindsIdOfPage(): void {
		$this->savePages();

		$this->assertEquals(
			new PageIdentifiers( new PageId( 42 ), 'Bar', 12 ),
			$this->newLookup( $this->getClient() )->getPageIdOfSubject( new SubjectId( self::GUID_2 ) )
		);
	}

	public function testFindsThePageOfEveryRequestedSubject(): void {
		$this->savePages();

		$this->assertEquals(
			[
				self::GUID_4 => new PageIdentifiers( new PageId( 1 ), 'Foo', 0 ),
				self::GUID_2 => new PageIdentifiers( new PageId( 42 ), 'Bar', 12 ),
				self::GUID_5 => new PageIdentifiers( new PageId( 32202 ), 'Baz', 0 ),
			],
			$this->newLookup()->getPageIdsOfSubjects(
				new SubjectIdList( [
					new SubjectId( self::GUID_4 ),
					new SubjectId( self::GUID_2 ),
					new SubjectId( self::GUID_5 ),
				] )
			)
		);
	}

	public function testOmitsSubjectsNoPageHosts(): void {
		$this->savePages();

		$this->assertSame(
			[ self::GUID_2 ],
			array_keys( $this->newLookup()->getPageIdsOfSubjects(
				new SubjectIdList( [
					new SubjectId( self::GUID_404 ),
					new SubjectId( self::GUID_2 ),
				] )
			) )
		);
	}

	/**
	 * Callers index the result by Subject id rather than by request position, so a caller that
	 * happens to collect its ids in a different order must not see a different result.
	 */
	public function testRequestOrderDoesNotChangeTheResult(): void {
		$this->savePages();

		$ids = [ new SubjectId( self::GUID_1 ), new SubjectId( self::GUID_3 ), new SubjectId( self::GUID_5 ) ];

		$this->assertEquals(
			$this->newLookup()->getPageIdsOfSubjects( new SubjectIdList( $ids ) ),
			$this->newLookup()->getPageIdsOfSubjects( new SubjectIdList( array_reverse( $ids ) ) )
		);
	}

	/**
	 * Subject nodes are merged on id alone and detached per page, so an id can carry a HasSubject
	 * edge from more than one page. Which of them it resolves to is unspecified, but the map is
	 * keyed by Subject id, so the second edge must not add a second entry or disturb the other ids.
	 */
	public function testSubjectHostedByTwoPagesYieldsOneEntry(): void {
		$this->savePages();
		$this->savePageHostingSubject( 77, self::GUID_4, 'Qux' );

		$this->assertSame(
			2,
			$this->countHostingEdges( self::GUID_4 ),
			'the fixture must leave two pages hosting the Subject, or this test asserts nothing'
		);

		$identifiers = $this->newLookup()->getPageIdsOfSubjects(
			new SubjectIdList( [ new SubjectId( self::GUID_4 ), new SubjectId( self::GUID_2 ) ] )
		);

		$this->assertCount( 2, $identifiers );
		$this->assertArrayHasKey( self::GUID_4, $identifiers );
		$this->assertContains( $identifiers[self::GUID_4]->getId()->id, [ 1, 77 ] );
		$this->assertEquals(
			new PageIdentifiers( new PageId( 42 ), 'Bar', 12 ),
			$identifiers[self::GUID_2] ?? null
		);
	}

	private function countHostingEdges( string $subjectId ): int {
		return $this->readGraph(
			'MATCH (:Page)-[:HasSubject]->(subject:Subject { id: $subjectId }) RETURN count(*) AS count',
			[ 'subjectId' => $subjectId ]
		)->first()->toRecursiveArray()['count'];
	}

	/**
	 * Validating a Subject that holds no relations asks for no ids at all, which is the common case,
	 * so it must not cost a query.
	 */
	public function testAsksTheGraphNothingWhenNoIdsAreRequested(): void {
		$client = $this->createStub( ClientInterface::class );
		$client->method( 'readTransaction' )->willThrowException( new RuntimeException( 'queried the graph' ) );

		$this->assertSame( [], $this->newLookup( $client )->getPageIdsOfSubjects( new SubjectIdList( [] ) ) );
	}

	/**
	 * Only sound for a page the graph does not hold yet: nothing is removed or detached, so the
	 * edge an earlier page already holds to the same Subject stays. Re-saving an existing page is
	 * not equivalent - dropping a Subject from a page that used to host it removes that Subject
	 * graph-wide, every other page's edge to it included.
	 */
	private function savePageHostingSubject( int $pageId, string $subjectId, string $title ): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: $pageId,
			properties: TestPageProperties::build( title: $title ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: $subjectId ),
			)
		) );
	}

	private function savePages(): void {
		$projectionStore = $this->newProjectionStore();

		$projectionStore->savePage( TestPage::build(
			id: 1,
			properties: TestPageProperties::build( title: 'Foo' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_4 ),
			)
		) );

		$projectionStore->savePage( TestPage::build(
			id: 42,
			properties: TestPageProperties::build( title: 'Bar', namespaceId: 12 ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_1 ),
				TestSubject::build( id: self::GUID_2 ),
				TestSubject::build( id: self::GUID_3 ),
			)
		) );

		$projectionStore->savePage( TestPage::build(
			id: 32202,
			properties: TestPageProperties::build( title: 'Baz' ),
			childSubjects: new SubjectMap(
				TestSubject::build( id: self::GUID_5 ),
			)
		) );
	}

}
