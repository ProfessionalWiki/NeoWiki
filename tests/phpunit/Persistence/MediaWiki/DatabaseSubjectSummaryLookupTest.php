<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SortDirection;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaries;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryCursor;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryQuery;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummarySearchTimeoutException;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummarySort;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectSummaryLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\Rdbms\DBQueryTimeoutError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LikeMatch;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Needs the integration base class for the pages whose saves fill the tables the lookup reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectSummaryLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LowestPageRows
 * @group Database
 */
class DatabaseSubjectSummaryLookupTest extends NeoWikiIntegrationTestCase {

	// Ids sort by creation time, so these four were created in this order.
	private const string OLDEST = 'sTestDSS1111111';
	private const string OLDER = 'sTestDSS1111112';
	private const string NEWER = 'sTestDSS1111113';
	private const string NEWEST = 'sTestDSS1111114';

	// Newer than every Subject that subjectsNewerThanTheFourAbove() builds.
	private const string NEWEST_OF_ALL = 'sTestDSS3333333';

	private const string EDITED = '20261001120000';

	public function testListsSubjectsNewestFirst(): void {
		$this->savePage( 'Page one', [ self::subject( self::OLDER, 'B' ), self::subject( self::NEWEST, 'D' ) ] );
		$this->savePage( 'Page two', [ self::subject( self::OLDEST, 'A' ), self::subject( self::NEWER, 'C' ) ] );

		$this->assertSame(
			[ self::NEWEST, self::NEWER, self::OLDER, self::OLDEST ],
			$this->idsOf( $this->summaries() )
		);
	}

	public function testASummaryDescribesTheSubjectAndItsPage(): void {
		$pageId = $this->savePage(
			'Help:Sinclair ZX Spectrum',
			[ self::subject( self::OLDEST, 'ZX Spectrum 48K' ) ],
			edited: '20261001140200'
		);

		$this->assertEquals(
			[
				new SubjectSummary(
					subjectId: self::OLDEST,
					displayName: 'ZX Spectrum 48K',
					displayNameIsGenerated: false,
					schemaName: 'Computer',
					pageId: $pageId,
					pageTitle: 'Help:Sinclair ZX Spectrum',
					lastEdited: '2026-10-01T14:02:00Z',
					hostSubject: null,
				),
			],
			$this->summaries()->summaries
		);
	}

	public function testAnUnlabelledSubjectThatIsNotMainIsNamedAfterItsSchema(): void {
		$this->savePage( 'Page one', [ self::subject( self::OLDEST, null ) ] );

		$summary = $this->summaries()->summaries[0];

		$this->assertSame( 'Computer', $summary->displayName );
		$this->assertTrue( $summary->displayNameIsGenerated );
	}

	/**
	 * @dataProvider schemaFilterSortProvider
	 */
	public function testKeepsOnlyTheSubjectsOfTheRequestedSchema( SubjectSummarySort $sort, SortDirection $direction ): void {
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum' ),
			self::subject( self::OLDER, null, 'Person' ),
			self::subject( self::NEWER, 'Galaksija' ),
		] );

		$this->assertEqualsCanonicalizing(
			[ self::NEWER, self::OLDEST ],
			$this->idsOf( $this->summaries( schema: 'Computer', sort: $sort, direction: $direction ) )
		);
	}

	public static function schemaFilterSortProvider(): iterable {
		yield 'newest' => [ SubjectSummarySort::Newest, SortDirection::Descending ];
		yield 'name, which reads unlabelled Subjects apart' => [ SubjectSummarySort::Name, SortDirection::Ascending ];
	}

	/**
	 * A quote would end a string written into the query, and a percent sign in a LIKE pattern matches any text.
	 */
	public function testMatchesTheRequestedSchemaNameCharacterForCharacter(): void {
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'A', "Rock 'n' Roll 100%" ),
			self::subject( self::OLDER, 'B', "Rock 'n' Roll 1000 hits" ),
		] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( schema: "Rock 'n' Roll 100%" ) ) );
	}

	public function testTheListingOfEverySchemaLeavesOutDependentSubjects(): void {
		$this->createExpansionCardSchema();
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum' ),
			self::subject( self::OLDER, null, 'Expansion card' ),
		] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries() ) );
	}

	public function testTheListingOfASchemaOfDependentSubjectsListsThem(): void {
		$this->createExpansionCardSchema();
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum' ),
			self::subject( self::OLDER, null, 'Expansion card' ),
		] );

		$this->assertSame( [ self::OLDER ], $this->idsOf( $this->summaries( schema: 'Expansion card' ) ) );
	}

	public function testTheListingOfEverySchemaReadsOnPastMoreDependentSubjectsThanTheScanBound(): void {
		$this->createExpansionCardSchema();
		$this->savePage( 'Page one', [ self::subject( self::OLDEST, 'ZX Spectrum' ) ] );
		$this->savePage( 'Page two', self::subjectsNewerThanTheFourAbove( 5, 'Expansion card' ) );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( scanBound: 3 ) ) );
	}

	public function testSearchMatchesANameAnywhereInAnyCase(): void {
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum 48K' ),
			self::subject( self::OLDER, 'Commodore 64' ),
		] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: 'SPECTRUM' ) ) );
	}

	public function testSearchMatchesAPageTitleAnywhereInAnyCase(): void {
		$this->savePage( 'Home computers of the 80s', [ self::subject( self::OLDEST, 'Galaksija' ) ] );
		$this->savePage( 'Storage', [ self::subject( self::OLDER, 'Apple IIe' ) ] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: 'COMPUTERS' ) ) );
	}

	/**
	 * In a LIKE pattern, a percent sign matches any text and an underscore any one character.
	 *
	 * @dataProvider likeWildcardProvider
	 */
	public function testSearchMatchesPercentSignsAndUnderscoresLiterally( string $search, string $match, string $nonMatch ): void {
		$this->savePage( 'Page one', [ self::subject( self::OLDEST, $match ), self::subject( self::OLDER, $nonMatch ) ] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: $search ) ) );
	}

	public static function likeWildcardProvider(): iterable {
		yield 'percent sign' => [ '100%', 'Rated 100%', 'Rated 1000 times' ];
		yield 'underscore' => [ 'a_b', 'Key a_b', 'Key axb' ];
	}

	public function testSearchMatchesTheStartOfAnId(): void {
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'A' ),
			self::subject( 'sAnotherS111111', 'B' ),
		] );

		$this->assertSame( [ self::OLDEST ], $this->idsOf( $this->summaries( search: 'sTestDSS' ) ) );
	}

	public function testSearchDoesNotMatchTheMiddleOfAnId(): void {
		$this->savePage( 'Page one', [ self::subject( self::OLDEST, 'A' ) ] );

		$this->assertSame( [], $this->idsOf( $this->summaries( search: 'TestDSS' ) ) );
	}

	public function testASearchThatTimesOutThrowsTheSearchTimeout(): void {
		$connectionProvider = $this->connectionProviderWhoseQueriesTimeOut();

		$this->expectException( SubjectSummarySearchTimeoutException::class );

		$this->summaries( search: 'Spectrum', connectionProvider: $connectionProvider );
	}

	public function testAListingWithoutASearchThatTimesOutThrowsTheDatabaseTimeout(): void {
		$connectionProvider = $this->connectionProviderWhoseQueriesTimeOut();

		$this->expectException( DBQueryTimeoutError::class );

		$this->summaries( connectionProvider: $connectionProvider );
	}

	public function testReadsOnInASecondFetchToFillThePage(): void {
		$this->savePage( 'Readable', [
			self::subject( self::NEWEST_OF_ALL, 'A' ),
			self::subject( self::OLDEST, 'B' ),
		] );
		// Between the two readable Subjects, and more rows than the first fetch reads.
		$hiddenPageId = $this->savePage( 'Unreadable', self::subjectsNewerThanTheFourAbove( 20 ) );

		$this->assertSame(
			[ self::NEWEST_OF_ALL, self::OLDEST ],
			$this->idsOf( $this->summaries(
				limit: 2,
				readAuthorizer: new SelectivePageReadAuthorizer( [ $hiddenPageId ] )
			) )
		);
	}

	public function testReadsNoFurtherThanTheScanBound(): void {
		$this->savePage( 'Readable', [ self::subject( self::OLDEST, 'A' ) ] );
		// As many rows as the bound, all read before the readable one.
		$hiddenPageId = $this->savePage( 'Unreadable', self::subjectsNewerThanTheFourAbove( 9 ) );

		$this->assertSame(
			[],
			$this->idsOf( $this->summaries(
				limit: 1,
				readAuthorizer: new SelectivePageReadAuthorizer( [ $hiddenPageId ] ),
				scanBound: 9
			) )
		);
	}

	public function testAFullPageFollowedOnlyByUnreadableRowsEndsTheListing(): void {
		$this->savePage( 'Page one', [ self::subject( self::NEWEST, 'D' ), self::subject( self::NEWER, 'C' ) ] );
		$hiddenPageId = $this->savePage( 'Page two', [ self::subject( self::OLDER, 'B' ), self::subject( self::OLDEST, 'A' ) ] );

		$page = $this->summaries( limit: 2, readAuthorizer: new SelectivePageReadAuthorizer( [ $hiddenPageId ] ) );

		$this->assertSame( [ self::NEWEST, self::NEWER ], $this->idsOf( $page ) );
		$this->assertNull( $page->nextCursor );
	}

	public function testTheScanBoundEndsTheListing(): void {
		// The bound is reached after one readable row and two of the five unreadable ones below it.
		$this->savePage( 'Visible', [
			self::subject( self::NEWEST_OF_ALL, 'A' ),
			self::subject( self::OLDEST, 'B' ),
		] );
		$hiddenPageId = $this->savePage( 'Hidden', self::subjectsNewerThanTheFourAbove( 5 ) );

		$page = $this->summaries(
			limit: 2,
			readAuthorizer: new SelectivePageReadAuthorizer( [ $hiddenPageId ] ),
			scanBound: 3
		);

		$this->assertSame( [ self::NEWEST_OF_ALL ], $this->idsOf( $page ) );
		$this->assertNull( $page->nextCursor );
	}

	/**
	 * Each page holds the Subject under a name and an edit time of its own, and the lowest page id is neither the
	 * first nor the last of the three pages to have stored its row.
	 *
	 * @dataProvider sortProvider
	 */
	public function testListsASubjectThatSeveralPagesHoldOnceUnderTheLowestPageId(
		SubjectSummarySort $sort,
		SortDirection $direction
	): void {
		$lowestPageId = $this->savePage( 'Lowest copy', [ self::subject( self::OLDEST, 'Old name' ) ] );
		$this->savePage( 'Middle copy', [ self::subject( self::OLDEST, 'Middle name' ) ], edited: '20261002120000' );
		$this->savePage( 'Highest copy', [ self::subject( self::OLDEST, 'Old name' ) ] );
		$this->savePage( 'Lowest copy', [ self::subject( self::OLDEST, 'Lowest name' ) ], edited: '20261003120000' );
		$this->savePage( 'Highest copy', [ self::subject( self::OLDEST, 'Highest name' ) ], edited: '20261004120000' );

		$summaries = $this->summaries( sort: $sort, direction: $direction )->summaries;

		$this->assertCount( 1, $summaries );
		$this->assertSame( $lowestPageId, $summaries[0]->pageId );
	}

	public function testSortsByNameIgnoringCaseWithGeneratedNamesLast(): void {
		$this->savePage( 'Page one', [
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
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'banana' ),
			self::subject( self::OLDER, null ),
			self::subject( self::NEWER, 'Apple' ),
		] );

		$this->assertSame(
			[ self::OLDEST, self::NEWER, self::OLDER ],
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Name, direction: SortDirection::Descending ) )
		);
	}

	/**
	 * @dataProvider nameTieProvider
	 * @param list<string> $expectedIds
	 */
	public function testBreaksNameTiesBySubjectIdInTheDirectionOfTheSort( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'ZX Spectrum' ),
			self::subject( self::OLDER, null ),
			self::subject( self::NEWER, 'ZX Spectrum' ),
			self::subject( self::NEWEST, null ),
		] );

		$this->assertSame(
			$expectedIds,
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Name, direction: $direction ) )
		);
	}

	public static function nameTieProvider(): iterable {
		yield 'ascending' => [ SortDirection::Ascending, [ self::OLDEST, self::NEWER, self::OLDER, self::NEWEST ] ];
		yield 'descending' => [ SortDirection::Descending, [ self::NEWER, self::OLDEST, self::NEWEST, self::OLDER ] ];
	}

	/**
	 * @dataProvider schemaOrderProvider
	 * @param list<string> $expectedIds
	 */
	public function testSortsBySchema( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'A', 'Person' ),
			self::subject( self::OLDER, 'B', 'Zebra' ),
			self::subject( self::NEWER, 'C', 'Computer' ),
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
	 * In byte order the capitalized "ALPHA zulu" would come first.
	 *
	 * @dataProvider pageTitleOrderProvider
	 * @param list<string> $expectedIds
	 */
	public function testSortsByPageTitleIgnoringCase( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 'ALPHA zulu', [ self::subject( self::OLDER, 'C' ) ] );
		$this->savePage( 'Alpha beta', [ self::subject( self::NEWER, 'A' ) ] );
		$this->savePage( 'Alpha mike', [ self::subject( self::OLDEST, 'B' ) ] );

		$this->assertSame(
			$expectedIds,
			$this->idsOf( $this->summaries( sort: SubjectSummarySort::Page, direction: $direction ) )
		);
	}

	public static function pageTitleOrderProvider(): iterable {
		yield 'ascending' => [ SortDirection::Ascending, [ self::NEWER, self::OLDEST, self::OLDER ] ];
		yield 'descending' => [ SortDirection::Descending, [ self::OLDER, self::OLDEST, self::NEWER ] ];
	}

	/**
	 * @dataProvider lastEditOrderProvider
	 * @param list<string> $expectedIds
	 */
	public function testSortsByLastEdit( SortDirection $direction, array $expectedIds ): void {
		$this->savePage( 'Page one', [ self::subject( self::OLDEST, 'A' ) ], edited: '20261001120000' );
		$this->savePage( 'Page two', [ self::subject( self::OLDER, 'B' ) ], edited: '20260901120000' );
		$this->savePage( 'Page three', [ self::subject( self::NEWER, 'C' ) ], edited: '20261101120000' );

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
		$this->savePage( 'Page one', [
			self::subject( self::OLDER, 'B' ),
			self::subject( self::NEWER, 'C' ),
			self::subject( self::NEWEST, 'D' ),
		] );

		$this->assertSame( [ self::NEWEST, self::NEWER ], $this->idsOf( $this->summaries( limit: 2 ) ) );
	}

	public function testTheLastPageHasNoNextCursor(): void {
		$this->savePage( 'Page one', [ self::subject( self::OLDEST, 'A' ), self::subject( self::OLDER, 'B' ) ] );

		$this->assertNull( $this->summaries( limit: 2 )->nextCursor );
	}

	/**
	 * @dataProvider sortProvider
	 */
	public function testFollowingTheCursorVisitsEverySubjectOnce( SubjectSummarySort $sort, SortDirection $direction ): void {
		// Each column sort has ties and changes of value, and with a limit of one the cursor crosses all of them.
		$this->savePage( 'Page one', [
			self::subject( self::OLDEST, 'Same', 'Zebra' ),
			self::subject( self::OLDER, null ),
		], edited: '20261001120000' );
		$this->savePage( 'Page two', [
			self::subject( self::NEWER, 'Same' ),
			self::subject( self::NEWEST, 'Other', 'Ant' ),
		], edited: '20261002120000' );
		$this->savePage( 'Page three', [ self::subject( 'sTestDSS1111115', null, 'Zebra' ) ], edited: '20260901120000' );

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
		yield 'schema ascending' => [ SubjectSummarySort::Schema, SortDirection::Ascending ];
		yield 'schema descending' => [ SubjectSummarySort::Schema, SortDirection::Descending ];
		yield 'page ascending' => [ SubjectSummarySort::Page, SortDirection::Ascending ];
		yield 'page descending' => [ SubjectSummarySort::Page, SortDirection::Descending ];
		yield 'edited ascending' => [ SubjectSummarySort::Edited, SortDirection::Ascending ];
		yield 'edited descending' => [ SubjectSummarySort::Edited, SortDirection::Descending ];
	}

	/**
	 * Holds the Subjects as other Subjects, so that none is named after the page unless it is the Main Subject.
	 *
	 * @param list<Subject> $otherSubjects
	 * @return int The page id
	 */
	private function savePage(
		string $pageName,
		array $otherSubjects,
		?Subject $mainSubject = null,
		string $edited = self::EDITED
	): int {
		ConvertibleTimestamp::setFakeTime( $edited );

		$revision = $this->createPageWithSubjects( $pageName, $mainSubject, new SubjectMap( ...$otherSubjects ) );
		$this->assertNotNull( $revision );

		return $revision->getPageId();
	}

	private function createExpansionCardSchema(): void {
		$this->assertNotNull( $this->createSchema( 'Expansion card', '{"subjectsAreDependent":true,"propertyDefinitions":{}}' ) );
	}

	private static function subject( string $id, ?string $label, string $schema = 'Computer' ): Subject {
		return TestSubject::build( id: $id, label: $label, schemaName: new SchemaName( $schema ) );
	}

	/**
	 * @return list<Subject> Up to 24 Subjects, newer than NEWEST and older than NEWEST_OF_ALL.
	 */
	private static function subjectsNewerThanTheFourAbove( int $count, string $schema = 'Computer' ): array {
		// Subject ids never contain I or O.
		$idEndings = array_slice( str_split( 'ABCDEFGHJKLMNPQRSTUVWXYZ' ), 0, $count );

		return array_map(
			static fn ( string $idEnding ): Subject => self::subject( 'sTestDSS222222' . $idEnding, $idEnding, $schema ),
			$idEndings
		);
	}

	private function connectionProviderWhoseQueriesTimeOut(): IConnectionProvider {
		$db = $this->createStub( IDatabase::class );
		$db->method( 'newSelectQueryBuilder' )->willReturnCallback( static fn () => new SelectQueryBuilder( $db ) );
		$db->method( 'anyString' )->willReturn( new LikeMatch( '%' ) );
		$db->method( 'select' )->willThrowException(
			new DBQueryTimeoutError( $db, 'Query execution was interrupted', 1969, 'SELECT', __METHOD__ )
		);

		$connectionProvider = $this->createStub( IConnectionProvider::class );
		$connectionProvider->method( 'getReplicaDatabase' )->willReturn( $db );

		return $connectionProvider;
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
		?IConnectionProvider $connectionProvider = null,
	): SubjectSummaries {
		$lookup = new DatabaseSubjectSummaryLookup(
			connectionProvider: $connectionProvider ?? $this->getServiceContainer()->getConnectionProvider(),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
			scanBound: $scanBound,
			searchTimeLimit: 0,
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

}
