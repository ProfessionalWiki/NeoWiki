<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\LatestRevisionSubjectLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\LatestRevisionSubjectLookup
 */
class LatestRevisionSubjectLookupTest extends TestCase {

	private const REQUESTED_ID = 's11111111111111';
	private const SAME_PAGE_ID = 's11111111111112';
	private const OTHER_PAGE_ID = 's11111111111113';

	private function newLookup( RevisionRecord $revision, Subject ...$otherPages ): LatestRevisionSubjectLookup {
		return new LatestRevisionSubjectLookup( $revision, new InMemorySubjectLookup( ...$otherPages ) );
	}

	private function newSubject( string $id, string $label ): Subject {
		return TestSubject::build( id: $id, label: new SubjectLabel( $label ) );
	}

	/**
	 * The Main Subject is the one a by-id read asks for most, and getAllSubjects() prepends it
	 * rather than holding it alongside the rest.
	 */
	private function newRevisionHolding( ?Subject $mainSubject, Subject ...$childSubjects ): RevisionRecord {
		$content = SubjectContent::newEmpty();
		$content->setPageSubjects( new PageSubjects( $mainSubject, new SubjectMap( ...$childSubjects ) ) );

		return $this->newRevisionWithSlotContent( $content );
	}

	private function newRevisionWithSlotContent( Content $content ): RevisionRecord {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getContent' )
			->with( MediaWikiSubjectRepository::SLOT_NAME )
			->willReturn( $content );

		return $revision;
	}

	private function labelsOf( SubjectMap $subjects ): array {
		return array_map( static fn ( Subject $subject ): ?string => $subject->getLabel()?->text, $subjects->asArray() );
	}

	public function testServesTheMainSubjectFromTheRevision(): void {
		$lookup = $this->newLookup(
			$this->newRevisionHolding( $this->newSubject( self::REQUESTED_ID, 'in the revision' ) ),
			$this->newSubject( self::REQUESTED_ID, 'from the other lookup' )
		);

		$this->assertSame(
			'in the revision',
			$lookup->getSubject( new SubjectId( self::REQUESTED_ID ) )?->getLabel()?->text
		);
	}

	public function testServesAChildSubjectFromTheRevision(): void {
		$lookup = $this->newLookup(
			$this->newRevisionHolding(
				$this->newSubject( self::REQUESTED_ID, 'main' ),
				$this->newSubject( self::SAME_PAGE_ID, 'in the revision' )
			),
			$this->newSubject( self::SAME_PAGE_ID, 'from the other lookup' )
		);

		$this->assertSame(
			'in the revision',
			$lookup->getSubject( new SubjectId( self::SAME_PAGE_ID ) )?->getLabel()?->text
		);
	}

	public function testAsksTheOtherLookupOnlyForIdsTheRevisionDoesNotHold(): void {
		$lookup = $this->newLookup(
			$this->newRevisionHolding(
				$this->newSubject( self::REQUESTED_ID, 'main' ),
				$this->newSubject( self::SAME_PAGE_ID, 'same page' )
			),
			$this->newSubject( self::OTHER_PAGE_ID, 'other page' ),
			$this->newSubject( self::SAME_PAGE_ID, 'must not be used' )
		);

		$subjects = $lookup->getSubjects( new SubjectIdList( [
			new SubjectId( self::REQUESTED_ID ),
			new SubjectId( self::SAME_PAGE_ID ),
			new SubjectId( self::OTHER_PAGE_ID ),
		] ) );

		$this->assertSame( [ 'main', 'same page', 'other page' ], $this->labelsOf( $subjects ) );
	}

	public function testAnEmptyIdListReadsNeitherSource(): void {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->expects( $this->never() )->method( 'getContent' );

		$this->assertTrue(
			( new LatestRevisionSubjectLookup( $revision, new InMemorySubjectLookup() ) )
				->getSubjects( new SubjectIdList( [] ) )
				->isEmpty()
		);
	}

	public function testFallsBackWhenTheRevisionHasNoSubjectSlot(): void {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getContent' )->willThrowException( new RevisionAccessException( 'No such slot' ) );

		$lookup = new LatestRevisionSubjectLookup(
			$revision,
			new InMemorySubjectLookup( $this->newSubject( self::REQUESTED_ID, 'from the other lookup' ) )
		);

		$this->assertSame(
			'from the other lookup',
			$lookup->getSubject( new SubjectId( self::REQUESTED_ID ) )?->getLabel()?->text
		);
	}

	/**
	 * A slot holding another content model stops a write, because a write would save over it, but a
	 * read shows the Subjects it can and leaves the rest to the pages that do answer.
	 */
	public function testFallsBackWhenTheSlotHoldsOtherContent(): void {
		$lookup = $this->newLookup(
			$this->newRevisionWithSlotContent( new TextContent( 'not a Subject' ) ),
			$this->newSubject( self::REQUESTED_ID, 'from the other lookup' )
		);

		$this->assertSame(
			'from the other lookup',
			$lookup->getSubject( new SubjectId( self::REQUESTED_ID ) )?->getLabel()?->text
		);
	}

	public function testReturnsNullWhenNeitherSourceHoldsTheSubject(): void {
		$lookup = $this->newLookup( $this->newRevisionHolding( $this->newSubject( self::REQUESTED_ID, 'main' ) ) );

		$this->assertNull( $lookup->getSubject( new SubjectId( self::OTHER_PAGE_ID ) ) );
	}

}
