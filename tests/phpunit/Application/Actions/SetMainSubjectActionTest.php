<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectAction;
use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectRequest;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject\SetMainSubjectAction
 */
class SetMainSubjectActionTest extends TestCase {

	private const string MAIN_ID = 's11111111111maa';
	private const string CHILD_ID = 's11111111111caa';
	private const string OTHER_ID = 's11111111111oaa';
	private const int PAGE_ID = 42;

	public function testPromotesChildToMainAndDemotesPreviousMain(): void {
		$repository = $this->newRepositoryWithMainAndChild();

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::CHILD_ID, comment: 'Promote child' )
		);

		$this->assertTrue( $presenter->changed );

		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame( self::CHILD_ID, $saved->getMainSubject()->id->text );
		$this->assertTrue( $saved->getChildSubjects()->hasSubject( TestSubject::build( id: self::MAIN_ID )->id ) );
		$this->assertSame( 'Promote child', $repository->comments[self::PAGE_ID] );
	}

	public function testClearingMainDemotesPreviousMainToChild(): void {
		$repository = $this->newRepositoryWithMainAndChild();

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: null )
		);

		$this->assertTrue( $presenter->changed );

		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertNull( $saved->getMainSubject() );
		$this->assertSame( 2, $saved->getChildSubjects()->count() );
		$this->assertTrue( $saved->getChildSubjects()->hasSubject( TestSubject::build( id: self::MAIN_ID )->id ) );
		$this->assertTrue( $saved->getChildSubjects()->hasSubject( TestSubject::build( id: self::CHILD_ID )->id ) );
	}

	public function testClearingMainOnPageWithoutMainIsNoOp(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build( id: self::CHILD_ID ) ) ),
			new PageId( self::PAGE_ID )
		);
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: null )
		);

		$this->assertTrue( $presenter->noChange );
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testPromotingTheCurrentMainIsNoOp(): void {
		$repository = $this->newRepositoryWithMainAndChild();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::MAIN_ID )
		);

		$this->assertTrue( $presenter->noChange );
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testPromotingUnknownSubjectIdReportsNotFound(): void {
		$repository = $this->newRepositoryWithMainAndChild();

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_ID )
		);

		$this->assertTrue( $presenter->notFound );

		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame( self::MAIN_ID, $saved->getMainSubject()->id->text );
	}

	public function testThrowsWhenUserMayReadButNotEditPage(): void {
		$action = new SetMainSubjectAction(
			presenter: $this->newSpyPresenter(),
			subjectRepository: new InMemorySubjectRepository(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false ),
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to change the main subject' );

		$action->setMainSubject( new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::CHILD_ID ) );
	}

	public function testReportsPageNotFoundWhenUserMayNotReadPage(): void {
		$repository = $this->newRepositoryWithMainAndChild();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );

		$presenter = $this->newSpyPresenter();

		( new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: new StubPageReadAuthorizer( allowed: false ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true ),
		) )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::CHILD_ID )
		);

		$this->assertTrue( $presenter->pageNotFound );
		// The page is left untouched: a denied read never reaches the write.
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReadDenialTakesPrecedenceOverWriteDenial(): void {
		// A page the caller can neither read nor edit answers not-found, never the write 403, so a
		// hidden page is indistinguishable from an absent one.
		$presenter = $this->newSpyPresenter();

		( new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->newRepositoryWithMainAndChild(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: false ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false ),
		) )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::CHILD_ID )
		);

		$this->assertTrue( $presenter->pageNotFound );
	}

	public function testReportsPageNotFoundWhenTheSaveFails(): void {
		// The page passed the read and write checks but is gone by the time the save runs: the
		// dropped write must be reported as not-found, never as changed.
		$repository = $this->newRepositoryWithMainAndChild();
		$repository->failNextSave = true;

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::CHILD_ID )
		);

		$this->assertTrue( $presenter->pageNotFound );
		$this->assertFalse( $presenter->changed );
	}

	private function newRepositoryWithMainAndChild(): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: self::MAIN_ID ),
				new SubjectMap( TestSubject::build( id: self::CHILD_ID ) )
			),
			new PageId( self::PAGE_ID )
		);
		return $repository;
	}

	private function newAction( SetMainSubjectPresenter $presenter, InMemorySubjectRepository $repository ): SetMainSubjectAction {
		return new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true ),
		);
	}

	private function newSpyPresenter(): object {
		return new class() implements SetMainSubjectPresenter {

			public bool $changed = false;
			public bool $noChange = false;
			public bool $notFound = false;
			public bool $pageNotFound = false;

			public function presentMainSubjectChanged(): void {
				$this->changed = true;
			}

			public function presentNoChange(): void {
				$this->noChange = true;
			}

			public function presentSubjectNotFound(): void {
				$this->notFound = true;
			}

			public function presentPageNotFound(): void {
				$this->pageNotFound = true;
			}

		};
	}

}
