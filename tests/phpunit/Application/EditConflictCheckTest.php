<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\EditConflictCheck;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\EditConflictCheck
 */
class EditConflictCheckTest extends TestCase {

	private const int PAGE_ID = 7;
	private const string SUBJECT_ID = 's11111111111127';

	private InMemorySubjectRepository $repository;

	public function setUp(): void {
		$this->repository = new InMemorySubjectRepository();
	}

	/**
	 * @return int The revision the page is at after the save.
	 */
	private function saveOnPage( int $pageId, Subject ...$subjects ): int {
		$this->repository->savePageSubjects( new PageSubjects( null, new SubjectMap( ...$subjects ) ), new PageId( $pageId ) );

		return $this->repository->getLatestRevisionId( new PageId( $pageId ) ) ?? 0;
	}

	private function subjectChangedSince( int $baseRevisionId ): bool {
		$check = new EditConflictCheck( $this->repository );

		return $check->changedSince(
			$baseRevisionId,
			$check->readLatest( new PageId( self::PAGE_ID ), new SubjectId( self::SUBJECT_ID ) ),
			new PageId( self::PAGE_ID ),
			static fn ( Subject $subject ): SubjectMap => new SubjectMap( $subject )
		);
	}

	public function testABaseRevisionOfAnotherPageCountsAsAChange(): void {
		$this->saveOnPage( self::PAGE_ID, TestSubject::build( id: self::SUBJECT_ID ) );
		$otherPageRevision = $this->saveOnPage( 6, TestSubject::build( id: 's11111111111126' ) );

		$this->assertTrue( $this->subjectChangedSince( $otherPageRevision ) );
	}

	public function testABaseRevisionWithoutTheSubjectCountsAsAChange(): void {
		$base = $this->saveOnPage( self::PAGE_ID, TestSubject::build( id: 's11111111111129' ) );
		$this->saveOnPage( self::PAGE_ID, TestSubject::build( id: 's11111111111129' ), TestSubject::build( id: self::SUBJECT_ID ) );

		$this->assertTrue( $this->subjectChangedSince( $base ) );
	}

	public function testAnAttemptOvertakenByAnotherSaveIsMadeAgain(): void {
		$outcomes = [ false, true ];

		$landed = ( new EditConflictCheck( $this->repository ) )->attemptUntilLanded(
			static function () use ( &$outcomes ): bool {
				return array_shift( $outcomes );
			}
		);

		$this->assertTrue( $landed );
	}

	public function testAWriteOvertakenThreeTimesGivesUp(): void {
		$outcomes = [ false, false, false, true ];

		$landed = ( new EditConflictCheck( $this->repository ) )->attemptUntilLanded(
			static function () use ( &$outcomes ): bool {
				return array_shift( $outcomes );
			}
		);

		$this->assertFalse( $landed );
	}

}
