<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingAction;
use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingRequest;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering\SetSubjectsOrderingAction
 */
class SetSubjectsOrderingActionTest extends TestCase {

	private const string MAIN_ID = 's11111111111maa';
	private const string FIRST_ID = 's11111111111aaa';
	private const string SECOND_ID = 's11111111111bbb';
	private const string THIRD_ID = 's11111111111ccc';
	private const string OTHER_ID = 's11111111111oth';
	private const int PAGE_ID = 42;

	public function testReordersChildSubjects(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::THIRD_ID, self::FIRST_ID, self::SECOND_ID ],
				comment: 'Reorder',
			)
		);

		$this->assertTrue( $presenter->changed );
		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame(
			[ self::THIRD_ID, self::FIRST_ID, self::SECOND_ID ],
			$saved->getChildSubjects()->getIdsAsTextArray()
		);
		$this->assertSame( 'Reorder', $repository->comments[self::PAGE_ID] );
	}

	public function testPromotesAndSwapsPreviousMainIntoSlot(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::SECOND_ID,
				childSubjectIds: [ self::FIRST_ID, self::MAIN_ID, self::THIRD_ID ],
			)
		);

		$this->assertTrue( $presenter->changed );
		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame( self::SECOND_ID, $saved->getMainSubject()->id->text );
		$this->assertSame(
			[ self::FIRST_ID, self::MAIN_ID, self::THIRD_ID ],
			$saved->getChildSubjects()->getIdsAsTextArray()
		);
	}

	public function testDemotesAtChosenPosition(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: null,
				childSubjectIds: [ self::FIRST_ID, self::MAIN_ID, self::SECOND_ID, self::THIRD_ID ],
			)
		);

		$this->assertTrue( $presenter->changed );
		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertNull( $saved->getMainSubject() );
		$this->assertSame(
			[ self::FIRST_ID, self::MAIN_ID, self::SECOND_ID, self::THIRD_ID ],
			$saved->getChildSubjects()->getIdsAsTextArray()
		);
	}

	public function testReportsNoChangeWhenOrderingMatchesCurrent(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::FIRST_ID, self::SECOND_ID, self::THIRD_ID ],
			)
		);

		$this->assertTrue( $presenter->noChange );
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReportsInvalidOrderingOnUnknownId(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::FIRST_ID, self::OTHER_ID, self::THIRD_ID ],
			)
		);

		$this->assertTrue( $presenter->invalid );
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReportsInvalidOrderingWhenChildIdMissing(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::FIRST_ID, self::SECOND_ID ],
			)
		);

		$this->assertTrue( $presenter->invalid );
	}

	public function testDemotesSingleMainWithNoOtherChildren(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects( TestSubject::build( id: self::MAIN_ID ), new SubjectMap() ),
			new PageId( self::PAGE_ID )
		);
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: null,
				childSubjectIds: [ self::MAIN_ID ],
			)
		);

		$this->assertTrue( $presenter->changed );
		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertNull( $saved->getMainSubject() );
		$this->assertSame( [ self::MAIN_ID ], $saved->getChildSubjects()->getIdsAsTextArray() );
	}

	public function testPromotesSingleChildOnPageWithNoMain(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build( id: self::FIRST_ID ) ) ),
			new PageId( self::PAGE_ID )
		);
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::FIRST_ID,
				childSubjectIds: [],
			)
		);

		$this->assertTrue( $presenter->changed );
		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame( self::FIRST_ID, $saved->getMainSubject()->id->text );
		$this->assertTrue( $saved->getChildSubjects()->isEmpty() );
	}

	public function testThrowsWhenUserMayReadButNotEditPage(): void {
		$action = new SetSubjectsOrderingAction(
			presenter: $this->newSpyPresenter(),
			subjectRepository: new InMemorySubjectRepository(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false ),
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to change the subject ordering' );

		$action->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: null,
				childSubjectIds: [],
			)
		);
	}

	public function testReportsPageNotFoundWhenUserMayNotReadPage(): void {
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$presenter = $this->newSpyPresenter();

		( new SetSubjectsOrderingAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: new StubPageReadAuthorizer( allowed: false ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true ),
		) )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::THIRD_ID, self::FIRST_ID, self::SECOND_ID ],
			)
		);

		$this->assertTrue( $presenter->pageNotFound );
		// A denied read never reaches the write: the ordering is left untouched.
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReadDenialTakesPrecedenceOverWriteDenial(): void {
		// A page the caller can neither read nor edit answers not-found, never the write 403, so a
		// hidden page is indistinguishable from an absent one.
		$presenter = $this->newSpyPresenter();

		( new SetSubjectsOrderingAction(
			presenter: $presenter,
			subjectRepository: $this->newRepositoryWithMainAndThreeChildren(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: false ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false ),
		) )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::THIRD_ID, self::FIRST_ID, self::SECOND_ID ],
			)
		);

		$this->assertTrue( $presenter->pageNotFound );
	}

	public function testReportsPageNotFoundWhenTheSaveFails(): void {
		// The page passed the read and write checks but is gone by the time the save runs: the
		// dropped write must be reported as not-found, never as changed.
		$repository = $this->newRepositoryWithMainAndThreeChildren();
		$repository->failNextSave = true;

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->setOrdering(
			new SetSubjectsOrderingRequest(
				pageId: self::PAGE_ID,
				mainSubjectId: self::MAIN_ID,
				childSubjectIds: [ self::THIRD_ID, self::FIRST_ID, self::SECOND_ID ],
			)
		);

		$this->assertTrue( $presenter->pageNotFound );
		$this->assertFalse( $presenter->changed );
	}

	private function newRepositoryWithMainAndThreeChildren(): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: self::MAIN_ID ),
				new SubjectMap(
					TestSubject::build( id: self::FIRST_ID ),
					TestSubject::build( id: self::SECOND_ID ),
					TestSubject::build( id: self::THIRD_ID ),
				)
			),
			new PageId( self::PAGE_ID )
		);
		return $repository;
	}

	private function newAction( SetSubjectsOrderingPresenter $presenter, InMemorySubjectRepository $repository ): SetSubjectsOrderingAction {
		return new SetSubjectsOrderingAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true ),
		);
	}

	private function newSpyPresenter(): object {
		return new class() implements SetSubjectsOrderingPresenter {

			public bool $changed = false;
			public bool $noChange = false;
			public bool $invalid = false;
			public bool $pageNotFound = false;
			public ?string $invalidReason = null;

			public function presentOrderingChanged(): void {
				$this->changed = true;
			}

			public function presentNoChange(): void {
				$this->noChange = true;
			}

			public function presentInvalidOrdering( string $reason ): void {
				$this->invalid = true;
				$this->invalidReason = $reason;
			}

			public function presentPageNotFound(): void {
				$this->pageNotFound = true;
			}

		};
	}

}
