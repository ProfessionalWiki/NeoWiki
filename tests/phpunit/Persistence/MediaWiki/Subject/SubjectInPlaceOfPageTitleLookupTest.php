<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use Closure;
use MediaWiki\Content\TextContent;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectInPlaceOfPageTitleLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * Needs the database for the published Subject index it reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectInPlaceOfPageTitleLookup
 * @group Database
 */
class SubjectInPlaceOfPageTitleLookupTest extends NeoWikiIntegrationTestCase {

	private const string EARLIER_ID = 's1zz1111111azz5';
	private const string MAIN_ID = 's1zz1111111azz6';
	private const string OTHER_ID = 's1zz1111111azz7';

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testAPageTitledByItsSubjectsIdIsNamedByItsMainSubjectsLabel(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );

		$subject = $this->subjectOf( $this->newLookup(), self::MAIN_ID );

		$this->assertSame( self::MAIN_ID, $subject?->id );
		$this->assertSame( 'Ada Lovelace', $subject?->label );
	}

	public function testPagesReadTogetherAreEachNamedByTheirMainSubject(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$this->savePage( self::OTHER_ID, 'Grace Hopper', self::OTHER_ID );

		$lookup = $this->lookupReadingUpFront( self::MAIN_ID, self::OTHER_ID );

		$this->assertSame( 'Ada Lovelace', $this->subjectOf( $lookup, self::MAIN_ID )?->label );
		$this->assertSame( 'Grace Hopper', $this->subjectOf( $lookup, self::OTHER_ID )?->label );
	}

	/**
	 * A list reads its pages once, up front, rather than once per link.
	 */
	public function testAPageReadUpFrontIsNotReadAgain(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$lookup = $this->lookupReadingUpFront( self::MAIN_ID );

		$this->truncateTable( DatabasePublishedSubjectIndex::SUBJECT_TABLE );

		$this->assertSame( 'Ada Lovelace', $this->subjectOf( $lookup, self::MAIN_ID )?->label );
	}

	public function testAPageTitledByAnyoneIsNotNamed(): void {
		$this->savePage( 'Standardization', 'Ada Lovelace' );

		$this->assertNull( $this->subjectOf( $this->newLookup(), 'Standardization' ) );
	}

	public function testAPageWithoutSubjectsIsNotNamed(): void {
		$this->editPage( self::MAIN_ID, new TextContent( 'No Subjects here' ) );

		$this->assertNull( $this->subjectOf( $this->lookupReadingUpFront( self::MAIN_ID ), self::MAIN_ID ) );
	}

	public function testAPageInTheSubjectNamespaceIsNamed(): void {
		$this->savePage( 'Subject:' . self::MAIN_ID, 'Ada Lovelace' );

		$this->assertSame( 'Ada Lovelace', $this->subjectOf( $this->newLookup(), 'Subject:' . self::MAIN_ID )?->label );
	}

	public function testAPageWhoseMainSubjectHasNoLabelIsNotNamed(): void {
		$this->savePage( self::MAIN_ID, null );

		$this->assertNull( $this->subjectOf( $this->newLookup(), self::MAIN_ID ) );
	}

	public function testAPageIsNamedByItsMainSubjectRatherThanByItsOtherSubjects(): void {
		$this->savePageWithOtherSubjects( 'Ada Lovelace' );

		$this->assertSame( 'Ada Lovelace', $this->subjectOf( $this->newLookup(), self::MAIN_ID )?->label );
	}

	public function testAPageWhoseMainSubjectHasNoLabelIsNotNamedByItsOtherSubjects(): void {
		$this->savePageWithOtherSubjects( null );

		$this->assertNull( $this->subjectOf( $this->newLookup(), self::MAIN_ID ) );
	}

	public function testAPageIsNamedByItsCurrentLabel(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$this->changeSubjectsOfPage( self::MAIN_ID, TestSubject::build( id: self::MAIN_ID, label: 'Countess' ) );

		$this->assertSame( 'Countess', $this->subjectOf( $this->newLookup(), self::MAIN_ID )?->label );
	}

	/**
	 * On a wiki with an approval extension, readers see the approved revision, not the latest draft.
	 */
	public function testAPageIsNamedAsTheWikiPublishesIt(): void {
		$approved = $this->createPageWithSubjects(
			self::MAIN_ID,
			TestSubject::build( id: self::MAIN_ID, label: 'Ada Lovelace' )
		);
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $approved ) );
		$this->changeSubjectsOfPage( self::MAIN_ID, TestSubject::build( id: self::MAIN_ID, label: 'Countess' ) );

		$this->assertSame( 'Ada Lovelace', $this->subjectOf( $this->newLookup(), self::MAIN_ID )?->label );
	}

	public function testEachReaderIsNamedThePageOnlyIfTheyMayReadIt(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$sysop = $this->getTestSysop()->getAuthority();

		$lookup = $this->newLookupReadBy(
			static fn ( Authority $reader ): StubPageReadAuthorizer => new StubPageReadAuthorizer(
				$reader->getUser()->getName() === $sysop->getUser()->getName()
			)
		);

		$this->assertNotNull( $this->subjectReadBy( $lookup, self::MAIN_ID, $sysop ) );
		$this->assertNull( $this->subjectReadBy( $lookup, self::MAIN_ID, $this->getTestUser()->getAuthority() ) );
	}

	/**
	 * Enhanced Recent changes links a page from every change to it, and from the group heading too.
	 */
	public function testManyLinksToOnePageCheckTheReadersAccessOnce(): void {
		$this->savePage( self::MAIN_ID, 'Ada Lovelace' );
		$authorizer = new SelectivePageReadAuthorizer( [] );

		$lookup = $this->newLookupReadBy( static fn (): SelectivePageReadAuthorizer => $authorizer );
		$this->subjectOf( $lookup, self::MAIN_ID );
		$this->subjectOf( $lookup, self::MAIN_ID );

		$this->assertCount( 1, $authorizer->checkedPageIds );
	}

	private function newLookup(): SubjectInPlaceOfPageTitleLookup {
		return $this->newLookupReadBy( static fn (): StubPageReadAuthorizer => new StubPageReadAuthorizer( true ) );
	}

	private function newLookupReadBy( Closure $newReadAuthorizer ): SubjectInPlaceOfPageTitleLookup {
		return new SubjectInPlaceOfPageTitleLookup( $this->getDb(), $newReadAuthorizer );
	}

	private function lookupReadingUpFront( string ...$pageNames ): SubjectInPlaceOfPageTitleLookup {
		$lookup = $this->newLookup();

		$lookup->prefetch( array_map(
			static fn ( string $pageName ): int => Title::newFromText( $pageName )->getArticleID(),
			$pageNames
		) );

		return $lookup;
	}

	private function subjectOf( SubjectInPlaceOfPageTitleLookup $lookup, string $pageName ): ?SubjectLabelLookupResult {
		return $this->subjectReadBy( $lookup, $pageName, $this->getTestUser()->getAuthority() );
	}

	private function subjectReadBy(
		SubjectInPlaceOfPageTitleLookup $lookup,
		string $pageName,
		Authority $reader
	): ?SubjectLabelLookupResult {
		return $lookup->forPage( Title::newFromText( $pageName ), $reader );
	}

	private function savePage( string $pageName, ?string $label, string $subjectId = self::MAIN_ID ): void {
		$this->createPageWithSubjects( $pageName, TestSubject::build( id: $subjectId, label: $label ) );
	}

	/**
	 * Labelled Subjects whose ids sort before and after the Main Subject's.
	 */
	private function savePageWithOtherSubjects( ?string $mainSubjectLabel ): void {
		$this->createPageWithSubjects(
			self::MAIN_ID,
			TestSubject::build( id: self::MAIN_ID, label: $mainSubjectLabel ),
			new SubjectMap(
				TestSubject::build( id: self::EARLIER_ID, label: 'Charles Babbage' ),
				TestSubject::build( id: self::OTHER_ID, label: 'Grace Hopper' )
			)
		);
	}

}
