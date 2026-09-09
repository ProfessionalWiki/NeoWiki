<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\NeoWiki\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Actions\DeleteSubject\DeleteSubjectAction
 */
class DeleteSubjectActionTest extends TestCase {

	private const string SUBJECT_ID = 's11111111111126';

	public function testDeleteSubjectRemovesSubjectFromRepository(): void {
		$repository = $this->newRepositoryWithSubject();

		$this->newAllowingAction( $repository )->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );

		$this->assertNull( $repository->getSubject( new SubjectId( self::SUBJECT_ID ) ) );
	}

	public function testDeleteSubjectPassesCommentThrough(): void {
		$repository = $this->newRepositoryWithSubject();

		$this->newAllowingAction( $repository )
			->deleteSubject( new SubjectId( self::SUBJECT_ID ), 'Removed by curator' );

		$this->assertSame( 'Removed by curator', $repository->comments[self::SUBJECT_ID] );
	}

	public function testAuthorizesAgainstTheSubjectsResolvedPage(): void {
		$authorizer = new SpySubjectWriteAuthorizer( allowed: true );
		$action = $this->newAction(
			$this->newRepositoryWithSubject(),
			$authorizer,
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 7 ), 'Owning page', 0 ) ]
			] )
		);

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );

		$this->assertEquals( new PageId( 7 ), $authorizer->authorizedPageId );
	}

	public function testGatesTheReadOnTheSubjectsResolvedPage(): void {
		$readAuthorizer = new SpyPageReadAuthorizer( allowed: true );

		$this->newAction(
			$this->newRepositoryWithSubject(),
			new SpySubjectWriteAuthorizer( allowed: true ),
			new InMemoryPageIdentifiersLookup( [
				[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 7 ), 'Owning page', 0 ) ]
			] ),
			readAuthorizer: $readAuthorizer
		)->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );

		$this->assertEquals( new PageId( 7 ), $readAuthorizer->authorizedPageId );
	}

	public function testThrowsWhenUserMayNotDeleteSubject(): void {
		$action = $this->newAction(
			new InMemorySubjectRepository(),
			new SpySubjectWriteAuthorizer( allowed: false ),
			$this->pageIdentifiersLookupWithSubject()
		);

		$this->expectException( SubjectEditNotAuthorizedException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to delete this subject' );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	/**
	 * A Subject on no page has no page rights to check, so it is answered as absent rather than as
	 * forbidden, like the write endpoints keyed by Subject id do.
	 */
	public function testUnresolvableSubjectIsReportedAsNotFound(): void {
		$action = $this->newAction(
			$this->newRepositoryWithSubject(),
			new SpySubjectWriteAuthorizer( allowed: true ),
			new InMemoryPageIdentifiersLookup()
		);

		$this->expectException( SubjectNotFoundException::class );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	public function testUnreadablePageAnswersNotFound(): void {
		$action = $this->newActionOnUnreadablePage( $this->newRepositoryWithSubject() );

		$this->expectException( SubjectNotFoundException::class );
		// Anchored: a read denial that added anything of its own would tell the two answers apart.
		$this->expectExceptionMessageMatches( '/^Subject not found: ' . self::SUBJECT_ID . '$/' );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	public function testUnreadablePageIsRejectedBeforeTheDeletion(): void {
		$repository = $this->newRepositoryWithSubject();

		try {
			$this->newActionOnUnreadablePage( $repository )
				->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
		} catch ( SubjectNotFoundException ) {
		}

		$this->assertNotNull( $repository->getSubject( new SubjectId( self::SUBJECT_ID ) ) );
	}

	public function testReadDenialTakesPrecedenceOverWriteDenial(): void {
		// A page the caller can neither read nor edit answers not-found, never the write 403, so a
		// hidden page is indistinguishable from an absent one.
		$action = $this->newAction(
			$this->newRepositoryWithSubject(),
			new SpySubjectWriteAuthorizer( allowed: false ),
			$this->pageIdentifiersLookupWithSubject(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: false )
		);

		$this->expectException( SubjectNotFoundException::class );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	public function testWriteThatRemovedNothingAnswersNotFound(): void {
		// Reporting success would tell the caller a Subject that is not there was deleted. Which of
		// the repository's reasons produced it is its own business, and pinned in its tests.
		$action = $this->newAction(
			new InMemorySubjectRepository(),
			new SpySubjectWriteAuthorizer( allowed: true ),
			$this->pageIdentifiersLookupWithSubject()
		);

		$this->expectException( SubjectNotFoundException::class );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	public function testPageLostUnderTheWriteAnswersNotFound(): void {
		// The page passed both checks and then went away before the save landed.
		$repository = $this->newRepositoryWithSubject();
		$repository->failNextSave = true;

		$action = $this->newAction(
			$repository,
			new SpySubjectWriteAuthorizer( allowed: true ),
			$this->pageIdentifiersLookupWithSubject()
		);

		$this->expectException( SubjectNotFoundException::class );

		$action->deleteSubject( new SubjectId( self::SUBJECT_ID ), null );
	}

	private function newRepositoryWithSubject(): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();
		$repository->updateSubject( TestSubject::build( id: self::SUBJECT_ID ) );
		return $repository;
	}

	/**
	 * For the cases about deletion itself rather than about the checks around it: the caller may write,
	 * and the Subject resolves to a page.
	 */
	private function newAllowingAction( SubjectRepository $repository ): DeleteSubjectAction {
		return $this->newAction(
			$repository,
			new SpySubjectWriteAuthorizer( allowed: true ),
			$this->pageIdentifiersLookupWithSubject()
		);
	}

	/**
	 * The caller may edit the Subject's page but may not read it: the case a missing read gate would
	 * let through.
	 */
	private function newActionOnUnreadablePage( SubjectRepository $repository ): DeleteSubjectAction {
		return $this->newAction(
			$repository,
			new SpySubjectWriteAuthorizer( allowed: true ),
			$this->pageIdentifiersLookupWithSubject(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: false )
		);
	}

	private function newAction(
		SubjectRepository $repository,
		SubjectWriteAuthorizer $authorizer,
		PageIdentifiersLookup $pageIdentifiersLookup,
		?PageReadAuthorizer $readAuthorizer = null,
	): DeleteSubjectAction {
		return new DeleteSubjectAction(
			subjectRepository: $repository,
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: $authorizer,
			pageIdentifiersLookup: $pageIdentifiersLookup
		);
	}

	private function pageIdentifiersLookupWithSubject(): InMemoryPageIdentifiersLookup {
		return new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 1 ), 'Test page', 0 ) ]
		] );
	}

}
