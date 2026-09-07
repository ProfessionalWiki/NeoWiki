<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectRequest;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use RuntimeException;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject\MoveSubjectAction
 */
class MoveSubjectActionTest extends TestCase {

	private const string MOVED_ID = 's11111111111maa';
	private const string SOURCE_MAIN_ID = 's11111111111saa';
	private const string TARGET_MAIN_ID = 's11111111111taa';
	private const string STRANGER_ID = 's11111111111zaa';

	private const int SOURCE_PAGE_ID = 42;
	private const int TARGET_PAGE_ID = 43;

	public function testMovedSubjectLeavesTheSourcePageAndArrivesOnTheTarget(): void {
		$repository = $this->newRepository();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->moved );

		$source = $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) );
		$this->assertFalse( $source->getAllSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );

		$target = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) );
		$this->assertTrue( $target->getChildSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );
	}

	public function testMovedSubjectKeepsItsIdAndContent(): void {
		$repository = $this->newRepository();

		$this->newAction( $this->newSpyPresenter(), $repository )->moveSubject( $this->newRequest() );

		$moved = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) )
			->getAllSubjects()->getSubject( new SubjectId( self::MOVED_ID ) );

		$this->assertEquals( TestSubject::build( id: self::MOVED_ID, label: 'Moved subject' ), $moved );
	}

	public function testMoveLeavesTheRestOfTheSourcePageAlone(): void {
		$repository = $this->newRepository();

		$this->newAction( $this->newSpyPresenter(), $repository )->moveSubject( $this->newRequest() );

		$source = $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) );
		$this->assertSame( self::SOURCE_MAIN_ID, $source->getMainSubject()->id->text );
	}

	public function testMoveWithoutPromotionLeavesTheTargetsMainSubjectInPlace(): void {
		$repository = $this->newRepository();

		$this->newAction( $this->newSpyPresenter(), $repository )->moveSubject( $this->newRequest() );

		$target = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) );
		$this->assertSame( self::TARGET_MAIN_ID, $target->getMainSubject()->id->text );
	}

	public function testPromotionMakesTheMovedSubjectMainAndDemotesThePreviousMain(): void {
		$repository = $this->newRepository();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->moveSubject(
			$this->newRequest( makeMainSubject: true )
		);

		$this->assertTrue( $presenter->moved );

		$target = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) );
		$this->assertSame( self::MOVED_ID, $target->getMainSubject()->id->text );
		$this->assertTrue( $target->getChildSubjects()->hasSubject( new SubjectId( self::TARGET_MAIN_ID ) ) );
	}

	public function testPromotionOntoATargetWithoutAMainSubjectAddsNoChild(): void {
		$repository = $this->newRepository( targetHasMainSubject: false );

		$this->newAction( $this->newSpyPresenter(), $repository )->moveSubject(
			$this->newRequest( makeMainSubject: true )
		);

		$target = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) );
		$this->assertSame( self::MOVED_ID, $target->getMainSubject()->id->text );
		$this->assertSame( 0, $target->getChildSubjects()->count() );
	}

	public function testMovingTheSourcePagesMainSubjectLeavesTheSourceWithoutOne(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects( TestSubject::build( id: self::MOVED_ID, label: 'Moved subject' ), new SubjectMap() ),
			new PageId( self::SOURCE_PAGE_ID )
		);
		$repository->savePageSubjects( PageSubjects::newEmpty(), new PageId( self::TARGET_PAGE_ID ) );

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->moved );
		$this->assertNull( $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) )->getMainSubject() );
	}

	public function testMovingToThePageTheSubjectIsAlreadyOnIsNoOp(): void {
		$repository = $this->newRepository();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->moveSubject(
			$this->newRequest( targetPageId: self::SOURCE_PAGE_ID )
		);

		$this->assertTrue( $presenter->noChange );
		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testSubjectHostedByNoPageReportsSubjectNotFound(): void {
		$repository = $this->newRepository();
		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->moveSubject(
			$this->newRequest( subjectId: self::STRANGER_ID )
		);

		$this->assertTrue( $presenter->subjectNotFound );
		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testSubjectMissingFromItsIndexedPageReportsSubjectNotFound(): void {
		// The index still names a page whose slot no longer holds the Subject.
		$repository = $this->newRepository();
		$repository->savePageSubjects(
			new PageSubjects( TestSubject::build( id: self::SOURCE_MAIN_ID ), new SubjectMap() ),
			new PageId( self::SOURCE_PAGE_ID )
		);
		$repository->savedPageIds = [];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->subjectNotFound );
		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testUnreadableSourcePageReportsSubjectNotFound(): void {
		// A page the caller may not read answers as an absent Subject, so page ids cannot be swept
		// for hidden pages through this route.
		$repository = $this->newRepository();
		$presenter = $this->newSpyPresenter();

		$this->newAction(
			$presenter,
			$repository,
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ self::SOURCE_PAGE_ID ] )
		)->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->subjectNotFound );
		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testUnreadableTargetPageReportsTargetPageNotFound(): void {
		$repository = $this->newRepository();
		$presenter = $this->newSpyPresenter();

		$this->newAction(
			$presenter,
			$repository,
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ self::TARGET_PAGE_ID ] )
		)->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->targetPageNotFound );
		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testReadDenialOnTheTargetTakesPrecedenceOverWriteDenial(): void {
		// A page the caller can neither read nor edit answers not-found, never the write 403, so a
		// hidden page is indistinguishable from an absent one.
		$presenter = $this->newSpyPresenter();

		$this->newAction(
			$presenter,
			$this->newRepository(),
			readAuthorizer: new SelectivePageReadAuthorizer( deniedPageIds: [ self::TARGET_PAGE_ID ] ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false )
		)->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->targetPageNotFound );
	}

	public function testThrowsWhenTheUserMayNotEditTheSourcePage(): void {
		$action = $this->newAction(
			$this->newSpyPresenter(),
			$this->newRepository(),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true, deniedPageIds: [ self::SOURCE_PAGE_ID ] )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to move this subject' );

		$action->moveSubject( $this->newRequest() );
	}

	public function testThrowsWhenTheUserMayNotEditTheTargetPage(): void {
		$action = $this->newAction(
			$this->newSpyPresenter(),
			$this->newRepository(),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true, deniedPageIds: [ self::TARGET_PAGE_ID ] )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to move this subject' );

		$action->moveSubject( $this->newRequest() );
	}

	public function testWriteDenialOnTheTargetLeavesTheSourcePageUnwritten(): void {
		// Both pages are authorized before either is written, so a target-page denial cannot leave
		// the source page already rewritten.
		$repository = $this->newRepository();

		try {
			$this->newAction(
				$this->newSpyPresenter(),
				$repository,
				writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true, deniedPageIds: [ self::TARGET_PAGE_ID ] )
			)->moveSubject( $this->newRequest() );
		} catch ( RuntimeException ) {
		}

		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testAuthorizesBothPagesExactlyOnce(): void {
		// Each authorization charges the edit rate limit, so a move must not ask more than twice.
		$writeAuthorizer = new SpySubjectWriteAuthorizer( allowed: true );

		$this->newAction( $this->newSpyPresenter(), $this->newRepository(), writeAuthorizer: $writeAuthorizer )
			->moveSubject( $this->newRequest() );

		$this->assertSame(
			[ self::SOURCE_PAGE_ID, self::TARGET_PAGE_ID ],
			array_map( static fn ( PageId $pageId ): int => $pageId->id, $writeAuthorizer->authorizedPageIds )
		);
	}

	public function testWritesTheSourcePageBeforeTheTargetPage(): void {
		// The graph projection is page-scoped, so the page written last decides what the moved
		// Subject's node looks like. Target-first leaves the node a stub belonging to no page.
		$repository = $this->newRepository();

		$this->newAction( $this->newSpyPresenter(), $repository )->moveSubject( $this->newRequest() );

		$this->assertSame( [ self::SOURCE_PAGE_ID, self::TARGET_PAGE_ID ], $repository->savedPageIds );
	}

	public function testFailingSourceSaveLeavesBothPagesUntouched(): void {
		// The source is written first, so its failure means nothing has been written at all.
		$repository = $this->newRepository();
		$repository->failSavesForPageIds = [ self::SOURCE_PAGE_ID ];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->sourcePageNotFound );
		$this->assertFalse( $presenter->moved );

		$source = $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) );
		$this->assertTrue( $source->getAllSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );

		$target = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) );
		$this->assertFalse( $target->getAllSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );
	}

	public function testFailingTargetSavePutsTheSubjectBackOnItsSourcePage(): void {
		// Nothing rolls the source write back on its own, and a Subject on no page cannot be written
		// through the API at all, so the action puts it back rather than stranding it.
		$repository = $this->newRepository();
		$repository->failSavesForPageIds = [ self::TARGET_PAGE_ID ];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->targetPageNotFound );
		$this->assertFalse( $presenter->moved );

		$source = $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) );
		$this->assertTrue( $source->getChildSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );
		$this->assertSame( self::SOURCE_MAIN_ID, $source->getMainSubject()->id->text );

		$target = $repository->getSubjectsByPageId( new PageId( self::TARGET_PAGE_ID ) );
		$this->assertFalse( $target->getAllSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );
	}

	public function testFailingTargetSavePutsTheSubjectBackWhereItSatAmongTheOthers(): void {
		// The rollback writes back the page as it was read, so a Subject taken from the middle of the
		// list returns to the middle rather than to the end.
		$first = 's11111111111aaa';
		$last = 's11111111111bbb';

		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: self::SOURCE_MAIN_ID ),
				new SubjectMap(
					TestSubject::build( id: $first ),
					TestSubject::build( id: self::MOVED_ID, label: 'Moved subject' ),
					TestSubject::build( id: $last )
				)
			),
			new PageId( self::SOURCE_PAGE_ID )
		);
		$repository->savePageSubjects( PageSubjects::newEmpty(), new PageId( self::TARGET_PAGE_ID ) );
		$repository->failSavesForPageIds = [ self::TARGET_PAGE_ID ];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->targetPageNotFound );

		$children = $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) )->getChildSubjects();
		$this->assertSame(
			[ $first, self::MOVED_ID, $last ],
			array_map( static fn ( $subject ): string => $subject->id->text, $children->asArray() )
		);
	}

	public function testFailingTargetSaveRestoresAMovedMainSubjectAsMainAgain(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects( TestSubject::build( id: self::MOVED_ID, label: 'Moved subject' ), new SubjectMap() ),
			new PageId( self::SOURCE_PAGE_ID )
		);
		$repository->savePageSubjects( PageSubjects::newEmpty(), new PageId( self::TARGET_PAGE_ID ) );
		$repository->failSavesForPageIds = [ self::TARGET_PAGE_ID ];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->targetPageNotFound );

		$source = $repository->getSubjectsByPageId( new PageId( self::SOURCE_PAGE_ID ) );
		$this->assertSame( self::MOVED_ID, $source->getMainSubject()?->id->text );
		$this->assertFalse( $source->getChildSubjects()->hasSubject( new SubjectId( self::MOVED_ID ) ) );
	}

	public function testReportsAnIncompleteMoveWhenTheSubjectCannotBePutBack(): void {
		// Both writes to the source page fail: it left the source and never reached the target, so
		// the caller is told rather than being handed a success for a Subject that is now nowhere.
		$repository = $this->newRepository();
		// Save 1 (source) lands, save 2 (target) fails, save 3 (putting it back) fails too.
		$repository->failSaveFromCallNumber = 2;

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->moveIncomplete );
		$this->assertFalse( $presenter->moved );
		$this->assertFalse( $presenter->targetPageNotFound );
		$this->assertSame(
			[ self::SOURCE_PAGE_ID, self::TARGET_PAGE_ID, self::SOURCE_PAGE_ID ],
			$repository->savedPageIds
		);
	}

	public function testSubjectAlreadyOnBothPagesIsRefused(): void {
		// The subject -> page index allows one id on two pages; a move must refuse that state rather
		// than compound it.
		$repository = $this->newRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: self::TARGET_MAIN_ID ),
				new SubjectMap( TestSubject::build( id: self::MOVED_ID, label: 'Moved subject' ) )
			),
			new PageId( self::TARGET_PAGE_ID )
		);
		$repository->savedPageIds = [];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->moveSubject( $this->newRequest() );

		$this->assertTrue( $presenter->alreadyOnTargetPage );
		$this->assertSame( [], $repository->savedPageIds );
	}

	public function testCommentIsUsedForBothPageSaves(): void {
		$repository = $this->newRepository();

		$this->newAction( $this->newSpyPresenter(), $repository )->moveSubject(
			$this->newRequest( comment: 'Filed under the right page' )
		);

		$this->assertSame( 'Filed under the right page', $repository->comments[self::SOURCE_PAGE_ID] );
		$this->assertSame( 'Filed under the right page', $repository->comments[self::TARGET_PAGE_ID] );
	}

	private function newRepository( bool $targetHasMainSubject = true ): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();

		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: self::SOURCE_MAIN_ID ),
				new SubjectMap( TestSubject::build( id: self::MOVED_ID, label: 'Moved subject' ) )
			),
			new PageId( self::SOURCE_PAGE_ID )
		);

		$repository->savePageSubjects(
			new PageSubjects(
				$targetHasMainSubject ? TestSubject::build( id: self::TARGET_MAIN_ID ) : null,
				new SubjectMap()
			),
			new PageId( self::TARGET_PAGE_ID )
		);

		$repository->savedPageIds = [];

		return $repository;
	}

	private function newRequest(
		string $subjectId = self::MOVED_ID,
		int $targetPageId = self::TARGET_PAGE_ID,
		bool $makeMainSubject = false,
		?string $comment = null
	): MoveSubjectRequest {
		return new MoveSubjectRequest(
			subjectId: $subjectId,
			targetPageId: $targetPageId,
			makeMainSubject: $makeMainSubject,
			comment: $comment,
		);
	}

	private function newAction(
		MoveSubjectPresenter $presenter,
		InMemorySubjectRepository $repository,
		?PageReadAuthorizer $readAuthorizer = null,
		?SpySubjectWriteAuthorizer $writeAuthorizer = null
	): MoveSubjectAction {
		return new MoveSubjectAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: $writeAuthorizer ?? new SpySubjectWriteAuthorizer( allowed: true ),
			pageIdentifiersLookup: new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::MOVED_ID ), new PageIdentifiers( new PageId( self::SOURCE_PAGE_ID ), 'Source page', 0 ) ],
				[ new SubjectId( self::SOURCE_MAIN_ID ), new PageIdentifiers( new PageId( self::SOURCE_PAGE_ID ), 'Source page', 0 ) ],
				[ new SubjectId( self::TARGET_MAIN_ID ), new PageIdentifiers( new PageId( self::TARGET_PAGE_ID ), 'Target page', 0 ) ],
			] ),
		);
	}

	private function newSpyPresenter(): object {
		return new class() implements MoveSubjectPresenter {

			public bool $moved = false;
			public bool $noChange = false;
			public bool $subjectNotFound = false;
			public bool $targetPageNotFound = false;
			public bool $sourcePageNotFound = false;
			public bool $alreadyOnTargetPage = false;
			public bool $moveIncomplete = false;

			public function presentMoved(): void {
				$this->moved = true;
			}

			public function presentNoChange(): void {
				$this->noChange = true;
			}

			public function presentSubjectNotFound(): void {
				$this->subjectNotFound = true;
			}

			public function presentTargetPageNotFound(): void {
				$this->targetPageNotFound = true;
			}

			public function presentSourcePageNotFound(): void {
				$this->sourcePageNotFound = true;
			}

			public function presentSubjectAlreadyOnTargetPage(): void {
				$this->alreadyOnTargetPage = true;
			}

			public function presentMoveIncomplete(): void {
				$this->moveIncomplete = true;
			}

		};
	}

}
