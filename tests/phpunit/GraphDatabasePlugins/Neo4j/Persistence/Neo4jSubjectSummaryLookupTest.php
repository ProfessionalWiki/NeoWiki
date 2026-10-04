<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SortDirection;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaries;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryCursor;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryQuery;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummarySort;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectSummaryLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPageProperties;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectSummaryLookup
 * @group Database
 */
class Neo4jSubjectSummaryLookupTest extends NeoWikiIntegrationTestCase {

	// Ids sort by creation time, so these four were created in this order.
	private const string OLDEST = 'sTestSSL1111111';
	private const string OLDER = 'sTestSSL1111112';
	private const string NEWER = 'sTestSSL1111113';
	private const string NEWEST = 'sTestSSL1111114';

	// Newer than every Subject that subjectsNewerThanTheFourAbove() builds.
	private const string NEWEST_OF_ALL = 'sTestSSL3333333';

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	protected function newProjectionStore(): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore(
			new InMemorySchemaLookup(
				TestSchema::build( name: 'Computer' ),
				TestSchema::build( name: 'Computer model' ),
				TestSchema::build( name: 'Person' ),
				TestSchema::build( name: 'Zebra' ),
				TestSchema::build( name: 'ant' ),
			)
		);
	}

	public function testListsSubjectsNewestFirst(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDER, 'B' ), self::subject( self::NEWEST, 'D' ) ] );
		$this->savePage( 2, 'Page two', [ self::subject( self::OLDEST, 'A' ), self::subject( self::NEWER, 'C' ) ] );

		$this->assertSame(
			[ self::NEWEST, self::NEWER, self::OLDER, self::OLDEST ],
			$this->idsOf( $this->summaries() )
		);
	}

	/**
	 * @dataProvider sortProvider
	 */
	public function testKeepsOnlyTheSubjectsOfTheRequestedSchema( SubjectSummarySort $sort, SortDirection $direction ): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum' ),
			self::subject( self::OLDER, 'Mira Zupan', 'Person' ),
			self::subject( self::NEWER, 'Galaksija' ),
		] );

		$this->assertEqualsCanonicalizing(
			[ self::NEWER, self::OLDEST ],
			$this->idsOf( $this->summaries( schema: 'Computer', sort: $sort, direction: $direction ) )
		);
	}

	public function testKeepsOnlyTheSubjectsOfARequestedSchemaWhoseNameHasASpace(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum', 'Computer model' ),
			self::subject( self::OLDER, 'Galaksija' ),
			self::subject( self::NEWER, 'Commodore 64', 'Computer model' ),
		] );

		$this->assertSame( [ self::NEWER, self::OLDEST ], $this->idsOf( $this->summaries( schema: 'Computer model' ) ) );
	}

	public function testASchemaNameThatSpellsCypherMatchesNoSubjects(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'ZX Spectrum' ) ] );

		// Inside a backtick-quoted name, Neo4j reads backslash-u0060 as the closing backtick.
		$schema = 'a' . chr( 92 ) . 'u0060 {wiki_id: $wikiId}) RETURN 1 AS x //';

		$this->assertSame( [], $this->idsOf( $this->summaries( schema: $schema ) ) );
	}

	public function testSearchMatchesANameAnywhereInAnyCase(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum 48K' ),
			self::subject( self::OLDER, 'Commodore 64' ),
		] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: 'spectrum' ) ) );
	}

	public function testSearchMatchesAPageTitleAnywhereInAnyCase(): void {
		$this->savePage( 1, 'Home computers of the 80s', [ self::subject( self::OLDEST, 'Galaksija' ) ] );
		$this->savePage( 2, 'Storage', [ self::subject( self::OLDER, 'Apple IIe' ) ] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: 'COMPUTERS' ) ) );
	}

	public function testSearchMatchesTheStartOfAnId(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'A' ),
			self::subject( 'sAnotherS111111', 'B' ),
		] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: 'sTestSSL' ) ) );
	}

	public function testSearchDoesNotMatchTheMiddleOfAnId(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'A' ) ] );

		$this->assertSame( [], $this->idsOf( $this->summaries( search: 'TestSSL' ) ) );
	}

	public function testSearchMatchesTheStartOfAnIdOnlyInItsCase(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'A' ) ] );

		$this->assertSame( [], $this->idsOf( $this->summaries( search: 'stestssl' ) ) );
	}

	public function testASummaryDescribesTheSubjectAndItsPage(): void {
		$this->savePage( 7, 'Sinclair ZX Spectrum', [ self::subject( self::OLDEST, 'ZX Spectrum 48K' ) ], modified: '20261001140200' );

		$this->assertEquals(
			[
				new SubjectSummary(
					subjectId: self::OLDEST,
					displayName: 'ZX Spectrum 48K',
					displayNameIsGenerated: false,
					schemaName: 'Computer',
					pageId: 7,
					pageTitle: 'Sinclair ZX Spectrum',
					lastEdited: '2026-10-01T14:02:00Z',
				),
			],
			$this->summaries()->summaries
		);
	}

	public function testAnUnlabelledMainSubjectIsNamedAfterItsPage(): void {
		$this->savePage( 1, 'Commodore 64', [], mainSubject: self::subject( self::OLDEST, null ) );

		$summary = $this->summaries()->summaries[0];

		$this->assertSame( 'Commodore 64', $summary->displayName );
		$this->assertFalse( $summary->displayNameIsGenerated );
	}

	public function testAnUnlabelledSubjectThatIsNotMainHasAGeneratedName(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, null ) ] );

		$summary = $this->summaries()->summaries[0];

		$this->assertSame( 'Computer', $summary->displayName );
		$this->assertTrue( $summary->displayNameIsGenerated );
	}

	public function testLeavesOutSubjectsOnPagesTheCallerCannotRead(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'A' ) ] );
		$this->savePage( 2, 'Page two', [ self::subject( self::OLDER, 'B' ) ] );

		$this->assertSame(
			[ self::OLDEST ],
			$this->idsOf( $this->summaries( readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] ) ) )
		);
	}

	public function testFillsThePageFromBeyondUnreadableRows(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'A' ), self::subject( self::OLDER, 'B' ) ] );
		$this->savePage( 2, 'Page two', [ self::subject( self::NEWER, 'C' ), self::subject( self::NEWEST, 'D' ) ] );

		$this->assertSame(
			[ self::OLDER, self::OLDEST ],
			$this->idsOf( $this->summaries(
				limit: 2,
				readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] )
			) )
		);
	}

	public function testReadsOnInASecondFetchToFillThePage(): void {
		$this->savePage( 1, 'Readable', [
			self::subject( self::NEWEST_OF_ALL, 'A' ),
			self::subject( self::OLDEST, 'B' ),
		] );
		// Between the two readable Subjects, and more rows than the first fetch reads.
		$this->savePage( 2, 'Unreadable', self::subjectsNewerThanTheFourAbove( 20 ) );

		$this->assertSame(
			[ self::NEWEST_OF_ALL, self::OLDEST ],
			$this->idsOf( $this->summaries(
				limit: 2,
				readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] )
			) )
		);
	}

	public function testReadsNoFurtherThanTheScanBound(): void {
		$this->savePage( 1, 'Readable', [ self::subject( self::OLDEST, 'A' ) ] );
		// As many rows as the bound, all read before the readable one.
		$this->savePage( 2, 'Unreadable', self::subjectsNewerThanTheFourAbove( 9 ) );

		$this->assertSame(
			[],
			$this->idsOf( $this->summaries(
				limit: 1,
				readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] ),
				scanBound: 9
			) )
		);
	}

	/**
	 * @dataProvider sortProvider
	 */
	public function testLeavesOutSubjectsOfOtherWikis( SubjectSummarySort $sort, SortDirection $direction ): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'Ours' ) ] );
		// Page ids repeat across a farm's wikis, so the other wiki's page has our page's id.
		$this->getClient()->run(
			'CREATE (:Page {id: 1, wiki_id: "otherwiki"})-[:HasSubject {isMain: false}]->'
				. '(:Subject:Computer {id: $id, name: "Theirs", wiki_id: "otherwiki"})',
			[ 'id' => self::NEWEST ]
		);

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( sort: $sort, direction: $direction ) ) );
	}

	/**
	 * @dataProvider sortProvider
	 */
	public function testListsASubjectThatSeveralPagesHoldOnceUnderTheLowestPageId( SubjectSummarySort $sort, SortDirection $direction ): void {
		$this->savePage( 9, 'Latest copy', [ self::subject( self::OLDEST, 'Shared' ) ] );
		$this->savePage( 3, 'Earliest copy', [ self::subject( self::OLDEST, 'Shared' ) ] );
		$this->savePage( 5, 'Middle copy', [ self::subject( self::OLDEST, 'Shared' ) ] );

		$summaries = $this->summaries( sort: $sort, direction: $direction )->summaries;

		$this->assertCount( 1, $summaries );
		$this->assertSame( 3, $summaries[0]->pageId );
	}

	public function testSortsByNameIgnoringCaseWithGeneratedNamesLast(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'banana' ),
			self::subject( self::OLDER, null ),
			self::subject( self::NEWER, 'Apple' ),
			self::subject( self::NEWEST, 'Cherry' ),
		] );

		$this->assertSame(
			[ self::NEWER, self::OLDEST, self::NEWEST, self::OLDER ],
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Name, direction: SortDirection::Ascending ) )
		);
	}

	public function testKeepsGeneratedNamesLastWhenNamesRunDescending(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'banana' ),
			self::subject( self::OLDER, null ),
			self::subject( self::NEWER, 'Apple' ),
		] );

		$this->assertSame(
			[ self::OLDEST, self::NEWER, self::OLDER ],
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Name, direction: SortDirection::Descending ) )
		);
	}

	public function testBreaksNameTiesByNewestFirst(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum' ),
			self::subject( self::NEWER, 'ZX Spectrum' ),
			self::subject( self::OLDER, 'ZX Spectrum' ),
		] );

		$this->assertSame(
			[ self::NEWER, self::OLDER, self::OLDEST ],
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Name, direction: SortDirection::Ascending ) )
		);
	}

	/**
	 * @dataProvider schemaOrderProvider
	 * @param list<string> $expectedIds
	 */
	public function testSortsBySchemaIgnoringCase( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'A', 'Computer' ),
			self::subject( self::OLDER, 'B', 'Zebra' ),
			self::subject( self::NEWER, 'C', 'ant' ),
		] );

		$this->assertSame(
			$expectedIds,
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Schema, direction: $direction ) )
		);
	}

	public static function schemaOrderProvider(): iterable {
		yield 'ascending' => [ SortDirection::Ascending, [ self::NEWER, self::OLDEST, self::OLDER ] ];
		yield 'descending' => [ SortDirection::Descending, [ self::OLDER, self::OLDEST, self::NEWER ] ];
	}

	/**
	 * @dataProvider pageTitleOrderProvider
	 * @param list<string> $expectedIds
	 */
	public function testSortsByPageTitleIgnoringCase( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 2, 'alpha', [ self::subject( self::OLDER, 'C' ) ] );
		$this->savePage( 3, 'Beta', [ self::subject( self::NEWER, 'A' ) ] );
		$this->savePage( 1, 'gamma', [ self::subject( self::OLDEST, 'B' ) ] );

		$this->assertSame(
			$expectedIds,
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Page, direction: $direction ) )
		);
	}

	public static function pageTitleOrderProvider(): iterable {
		yield 'ascending' => [ SortDirection::Ascending, [ self::OLDER, self::NEWER, self::OLDEST ] ];
		yield 'descending' => [ SortDirection::Descending, [ self::OLDEST, self::NEWER, self::OLDER ] ];
	}

	/**
	 * @dataProvider lastEditOrderProvider
	 * @param list<string> $expectedIds
	 */
	public function testSortsByLastEdit( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'A' ) ], modified: '20261001120000' );
		$this->savePage( 2, 'Page two', [ self::subject( self::OLDER, 'B' ) ], modified: '20260901120000' );
		$this->savePage( 3, 'Page three', [ self::subject( self::NEWER, 'C' ) ], modified: '20261101120000' );

		$this->assertSame(
			$expectedIds,
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Edited, direction: $direction ) )
		);
	}

	public static function lastEditOrderProvider(): iterable {
		yield 'oldest first' => [ SortDirection::Ascending, [ self::OLDER, self::OLDEST, self::NEWER ] ];
		yield 'newest first' => [ SortDirection::Descending, [ self::NEWER, self::OLDEST, self::OLDER ] ];
	}

	public function testAPageHoldsNoMoreSubjectsThanTheLimit(): void {
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDER, 'B' ),
			self::subject( self::NEWER, 'C' ),
			self::subject( self::NEWEST, 'D' ),
		] );

		$this->assertSame( [ self::NEWEST, self::NEWER ], $this->idsOf( $this->summaries( limit: 2 ) ) );
	}

	public function testTheLastPageHasNoNextCursor(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::OLDEST, 'A' ), self::subject( self::OLDER, 'B' ) ] );

		$this->assertNull( $this->summaries( limit: 2 )->nextCursor );
	}

	/**
	 * @dataProvider sortProvider
	 */
	public function testFollowingTheCursorVisitsEverySubjectOnce( SubjectSummarySort $sort, SortDirection $direction ): void {
		// Each column sort has ties and changes of value, and with a limit of one the cursor crosses all of them.
		$this->savePage( 1, 'Page one', [
			self::subject( self::OLDEST, 'Same', 'Zebra' ),
			self::subject( self::OLDER, null ),
		], modified: '20261001120000' );
		$this->savePage( 2, 'Page two', [
			self::subject( self::NEWER, 'Same' ),
			self::subject( self::NEWEST, 'Other', 'ant' ),
		], modified: '20261002120000' );
		$this->savePage( 3, 'Page three', [ self::subject( 'sTestSSL1111115', null, 'Zebra' ) ], modified: '20260901120000' );

		$visited = [];
		$after = null;

		// Bounded, so a cursor that leads back fails the test rather than looping forever.
		do {
			$page = $this->summaries( sort: $sort, direction: $direction, after: $after, limit: 1 );
			$visited = array_merge( $visited, $this->idsOf( $page ) );
			$after = $page->nextCursor;
		} while ( $after !== null && count( $visited ) <= 5 );

		$this->assertSame( $this->idsOf( $this->summaries( sort: $sort, direction: $direction ) ), $visited );
		$this->assertCount( 5, array_unique( $visited ) );
	}

	public static function sortProvider(): iterable {
		yield 'newest' => [ SubjectSummarySort::Newest, SortDirection::Descending ];
		yield 'name ascending' => [ SubjectSummarySort::Name, SortDirection::Ascending ];
		yield 'name descending' => [ SubjectSummarySort::Name, SortDirection::Descending ];
		yield 'schema descending' => [ SubjectSummarySort::Schema, SortDirection::Descending ];
		yield 'page ascending' => [ SubjectSummarySort::Page, SortDirection::Ascending ];
		yield 'edited descending' => [ SubjectSummarySort::Edited, SortDirection::Descending ];
	}

	public function testAFullPageFollowedOnlyByUnreadableRowsEndsTheListing(): void {
		$this->savePage( 1, 'Page one', [ self::subject( self::NEWEST, 'D' ), self::subject( self::NEWER, 'C' ) ] );
		$this->savePage( 2, 'Page two', [ self::subject( self::OLDER, 'B' ), self::subject( self::OLDEST, 'A' ) ] );

		$page = $this->summaries( limit: 2, readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] ) );

		$this->assertSame( [ self::NEWEST, self::NEWER ], $this->idsOf( $page ) );
		$this->assertNull( $page->nextCursor );
	}

	public function testTheScanBoundEndsTheListing(): void {
		// The bound is reached after one readable row and two of the five unreadable ones below it.
		$this->savePage( 1, 'Visible', [
			self::subject( self::NEWEST_OF_ALL, 'A' ),
			self::subject( self::OLDEST, 'B' ),
		] );
		$this->savePage( 2, 'Hidden', self::subjectsNewerThanTheFourAbove( 5 ) );

		$page = $this->summaries(
			limit: 2,
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ 2 ] ),
			scanBound: 3
		);

		$this->assertSame( [ self::NEWEST_OF_ALL ], $this->idsOf( $page ) );
		$this->assertNull( $page->nextCursor );
	}

	/**
	 * @param list<Subject> $otherSubjects
	 */
	private function savePage(
		int $id,
		string $title,
		array $otherSubjects,
		?Subject $mainSubject = null,
		string $modified = '20261001120000'
	): void {
		$this->newProjectionStore()->savePage( TestPage::build(
			id: $id,
			properties: TestPageProperties::build( title: $title, modificationTime: $modified ),
			mainSubject: $mainSubject,
			otherSubjects: new SubjectMap( ...$otherSubjects ),
		) );
	}

	private static function subject( string $id, ?string $label, string $schema = 'Computer' ): Subject {
		return TestSubject::build( id: $id, label: $label, schemaName: new SchemaName( $schema ) );
	}

	/**
	 * @return list<Subject> Up to 24 Subjects, newer than NEWEST and older than NEWEST_OF_ALL.
	 */
	private static function subjectsNewerThanTheFourAbove( int $count ): array {
		// Subject ids never contain I or O.
		$idEndings = array_slice( str_split( 'ABCDEFGHJKLMNPQRSTUVWXYZ' ), 0, $count );

		return array_map(
			static fn ( string $idEnding ): Subject => self::subject( 'sTestSSL222222' . $idEnding, $idEnding ),
			$idEndings
		);
	}

	private function summaries(
		?string $schema = null,
		string $search = '',
		SubjectSummarySort $sort = SubjectSummarySort::Newest,
		SortDirection $direction = SortDirection::Descending,
		?SubjectSummaryCursor $after = null,
		int $limit = 10,
		?PageReadAuthorizer $readAuthorizer = null,
		int $scanBound = 1000,
	): SubjectSummaries {
		$lookup = new Neo4jSubjectSummaryLookup(
			client: $this->getClient(),
			wikiId: NeoWikiExtension::getInstance()->config->wikiId,
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			scanBound: $scanBound,
		);

		return $lookup->getSubjectSummaries( new SubjectSummaryQuery(
			$schema === null ? null : new SchemaName( $schema ), $search, $sort, $direction, $after, $limit
		) );
	}

	/**
	 * @return list<string>
	 */
	private function idsOf( SubjectSummaries $summaries ): array {
		return array_map( static fn ( SubjectSummary $summary ): string => $summary->subjectId, $summaries->summaries );
	}

	private function getClient(): ClientInterface {
		return NeoWikiExtension::getInstance()->getNeo4jClient();
	}

}
