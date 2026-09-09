<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\ReadAuthorizedSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\ReadAuthorizedSubjectLookup
 */
class ReadAuthorizedSubjectLookupTest extends TestCase {

	private const string READABLE_ID = 'srt111111111aaa';
	private const string SHARES_READABLE_PAGE_ID = 'srt111111111ddd';
	private const string RESTRICTED_ID = 'srt111111111bbb';
	private const string UNHOSTED_ID = 'srt111111111ccc';

	private const int READABLE_PAGE_ID = 1;
	private const int RESTRICTED_PAGE_ID = 2;

	private InMemorySubjectLookup $innerLookup;
	private InMemoryPageIdentifiersLookup $pageIdentifiersLookup;
	private CountingPageReadAuthorizer $readAuthorizer;

	protected function setUp(): void {
		$this->innerLookup = new InMemorySubjectLookup(
			TestSubject::build( id: self::READABLE_ID ),
			TestSubject::build( id: self::SHARES_READABLE_PAGE_ID ),
			TestSubject::build( id: self::RESTRICTED_ID ),
			TestSubject::build( id: self::UNHOSTED_ID ),
		);

		$this->pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( self::READABLE_ID ), $this->newPageIdentifiers( self::READABLE_PAGE_ID ) ],
			[ new SubjectId( self::SHARES_READABLE_PAGE_ID ), $this->newPageIdentifiers( self::READABLE_PAGE_ID ) ],
			[ new SubjectId( self::RESTRICTED_ID ), $this->newPageIdentifiers( self::RESTRICTED_PAGE_ID ) ],
		] );

		$this->readAuthorizer = new CountingPageReadAuthorizer( deniedPageId: self::RESTRICTED_PAGE_ID );
	}

	private function newLookup(): ReadAuthorizedSubjectLookup {
		return new ReadAuthorizedSubjectLookup(
			subjectLookup: $this->innerLookup,
			pageIdentifiersLookup: $this->pageIdentifiersLookup,
			readAuthorizer: $this->readAuthorizer,
		);
	}

	private function newPageIdentifiers( int $pageId ): PageIdentifiers {
		return new PageIdentifiers( new PageId( $pageId ), 'Page ' . $pageId, 0 );
	}

	private function idList( string ...$ids ): SubjectIdList {
		return new SubjectIdList( array_map(
			static fn ( string $id ): SubjectId => new SubjectId( $id ),
			$ids
		) );
	}

	public function testSubjectOnAReadablePageIsReturned(): void {
		$subjects = $this->newLookup()->getSubjects( $this->idList( self::READABLE_ID ) );

		$this->assertSame( [ self::READABLE_ID ], $subjects->getIdsAsTextArray() );
	}

	public function testSubjectOnAnUnreadablePageIsAbsentJustLikeOneThatDoesNotExist(): void {
		$subjects = $this->newLookup()->getSubjects(
			$this->idList( self::READABLE_ID, self::RESTRICTED_ID )
		);

		$this->assertSame( [ self::READABLE_ID ], $subjects->getIdsAsTextArray() );
	}

	public function testSubjectOnAnUnreadablePageIsNotFoundById(): void {
		$this->assertNull( $this->newLookup()->getSubject( new SubjectId( self::RESTRICTED_ID ) ) );
	}

	public function testSubjectOnAReadablePageIsFoundById(): void {
		$subject = $this->newLookup()->getSubject( new SubjectId( self::READABLE_ID ) );

		$this->assertNotNull( $subject );
		$this->assertSame( self::READABLE_ID, $subject->id->text );
	}

	/**
	 * Withheld rather than served ungated: nothing says a wrapped lookup reaches Subjects only
	 * through the index this gate reads, and an id that index does not carry has no page whose
	 * permissions were checked.
	 */
	public function testSubjectHostedByNoPageIsWithheld(): void {
		$subjects = $this->newLookup()->getSubjects( $this->idList( self::UNHOSTED_ID ) );

		$this->assertTrue( $subjects->isEmpty() );
	}

	public function testEveryRequestedIdIsStillReturnedWhenAllArePermitted(): void {
		$subjects = $this->newLookup()->getSubjects(
			$this->idList( self::READABLE_ID, self::SHARES_READABLE_PAGE_ID )
		);

		$this->assertSame(
			[ self::READABLE_ID, self::SHARES_READABLE_PAGE_ID ],
			$subjects->getIdsAsTextArray()
		);
	}

	public function testHostingPagesAreResolvedInOneLookup(): void {
		$this->newLookup()->getSubjects(
			$this->idList( self::READABLE_ID, self::RESTRICTED_ID, self::UNHOSTED_ID )
		);

		$this->assertSame( 1, $this->pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );
		$this->assertSame( 0, $this->pageIdentifiersLookup->getPageIdOfSubjectCallCount );
	}

	/**
	 * Each check loads the page row and runs the full permission hook, so Subjects sharing a
	 * hosting page must not each pay for it.
	 */
	public function testPermissionOfAPageSharedByTwoSubjectsIsAskedOnce(): void {
		$this->newLookup()->getSubjects(
			$this->idList( self::READABLE_ID, self::SHARES_READABLE_PAGE_ID )
		);

		$this->assertSame( [ self::READABLE_PAGE_ID ], $this->readAuthorizer->checkedPageIds );
	}

	public function testNoPageIsAuthorizedForAnEmptyIdList(): void {
		$subjects = $this->newLookup()->getSubjects( new SubjectIdList( [] ) );

		$this->assertTrue( $subjects->isEmpty() );
		$this->assertSame( [], $this->readAuthorizer->checkedPageIds );
	}

}

/**
 * Denies one page id and records every page it was asked about, so a caller that asks per Subject
 * rather than per page is visible.
 */
class CountingPageReadAuthorizer implements PageReadAuthorizer {

	/**
	 * @var int[]
	 */
	public array $checkedPageIds = [];

	public function __construct(
		private readonly int $deniedPageId
	) {
	}

	public function authorizeReadByPageId( PageId $pageId ): bool {
		$this->checkedPageIds[] = $pageId->id;

		return $pageId->id !== $this->deniedPageId;
	}

	public function authorizeReadByPageTitle( Title $title ): bool {
		return $this->authorizeReadByPageId( new PageId( $title->getId() ) );
	}

}
