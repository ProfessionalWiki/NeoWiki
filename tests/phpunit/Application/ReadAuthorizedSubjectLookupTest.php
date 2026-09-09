<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\ReadAuthorizedSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\ReadAuthorizedSubjectLookup
 */
class ReadAuthorizedSubjectLookupTest extends TestCase {

	private const string READABLE_ID = 'srt111111111aaa';
	private const string RESTRICTED_ID = 'srt111111111bbb';
	private const string UNHOSTED_ID = 'srt111111111ccc';

	private const int READABLE_PAGE_ID = 1;
	private const int RESTRICTED_PAGE_ID = 2;

	private InMemorySubjectLookup $innerLookup;

	protected function setUp(): void {
		$this->innerLookup = new InMemorySubjectLookup(
			TestSubject::build( id: self::READABLE_ID ),
			TestSubject::build( id: self::RESTRICTED_ID ),
			TestSubject::build( id: self::UNHOSTED_ID ),
		);
	}

	private function newLookup(): ReadAuthorizedSubjectLookup {
		return new ReadAuthorizedSubjectLookup(
			subjectLookup: $this->innerLookup,
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::READABLE_ID ), $this->newPageIdentifiers( self::READABLE_PAGE_ID ) ],
				[ new SubjectId( self::RESTRICTED_ID ), $this->newPageIdentifiers( self::RESTRICTED_PAGE_ID ) ],
			] ),
			readAuthorizer: new SelectivePageReadAuthorizer( [ self::RESTRICTED_PAGE_ID ] ),
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
	 * A Subject no page hosts is what a caller reading an old revision sees, and what the
	 * repository-backed lookups report as absent anyway. Denying it here would hide Subjects the
	 * caller may read, so the inner lookup decides, exactly as GetSubjectQuery lets it.
	 */
	public function testSubjectHostedByNoPageIsLeftToTheInnerLookup(): void {
		$subjects = $this->newLookup()->getSubjects( $this->idList( self::UNHOSTED_ID ) );

		$this->assertSame( [ self::UNHOSTED_ID ], $subjects->getIdsAsTextArray() );
	}

	/**
	 * Filtering before the fetch rather than after it means no revision is loaded and no slot
	 * deserialized for a page the caller may not read.
	 */
	public function testUnreadableIdsNeverReachTheInnerLookup(): void {
		$this->newLookup()->getSubjects( $this->idList( self::READABLE_ID, self::RESTRICTED_ID ) );

		$this->assertSame( [ [ self::READABLE_ID ] ], $this->innerLookup->requestedIdBatches );
	}

	public function testEveryIdIsAuthorizedInOneLookup(): void {
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( self::READABLE_ID ), $this->newPageIdentifiers( self::READABLE_PAGE_ID ) ],
			[ new SubjectId( self::RESTRICTED_ID ), $this->newPageIdentifiers( self::RESTRICTED_PAGE_ID ) ],
		] );

		$lookup = new ReadAuthorizedSubjectLookup(
			subjectLookup: $this->innerLookup,
			pageIdentifiersLookup: $pageIdentifiersLookup,
			readAuthorizer: new SelectivePageReadAuthorizer( [ self::RESTRICTED_PAGE_ID ] ),
		);

		$lookup->getSubjects( $this->idList( self::READABLE_ID, self::RESTRICTED_ID, self::UNHOSTED_ID ) );

		$this->assertSame( 1, $pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );
		$this->assertSame( 0, $pageIdentifiersLookup->getPageIdOfSubjectCallCount );
	}

}
