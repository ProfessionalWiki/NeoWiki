<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

/**
 * Moves a Subject from the page holding it to another page, keeping its id so relations pointing at
 * it keep resolving.
 *
 * A move is two page edits, and there is no cross-page write primitive: PageUpdater commits within
 * the request, and only an exception escaping the handler would undo it. So the two writes are
 * ordered and the first is undone by hand when the second fails, rather than relying on a
 * transaction to unwind them together.
 *
 * The source page is written first. The graph projection is page-scoped: every page write rewrites
 * the nodes of the Subjects that page holds and tears down the ones it no longer does, so the page
 * written last is the one that decides what the moved Subject's node looks like. Writing the source
 * last would strip the node the target write had just built, leaving the Subject a bare stub that
 * belongs to no page until the next full rebuild.
 */
readonly class MoveSubjectAction {

	public function __construct(
		private MoveSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	public function moveSubject( MoveSubjectRequest $request ): void {
		$subjectId = new SubjectId( $request->subjectId );
		$targetPageId = new PageId( $request->targetPageId );

		$sourcePageId = $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();

		// A Subject on no page has no page rights to check, so it is answered as absent.
		if ( $sourcePageId === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		// Gate on read before write, for both pages, and before the no-op short-circuit below: a page
		// the caller may not read and a page that does not exist answer the same shape, so neither
		// page id can be swept for hidden pages.
		if ( !$this->readAuthorizer->authorizeReadByPageId( $sourcePageId ) ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		if ( !$this->readAuthorizer->authorizeReadByPageId( $targetPageId ) ) {
			$this->presenter->presentTargetPageNotFound();
			return;
		}

		if ( $sourcePageId->id === $targetPageId->id ) {
			$this->presenter->presentNoChange();
			return;
		}

		$sourceSubjects = $this->subjectRepository->getSubjectsByPageId( $sourcePageId );
		$subject = $sourceSubjects->getAllSubjects()->getSubject( $subjectId );

		// The index named this page, but its slot no longer holds the Subject.
		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$targetSubjects = $this->subjectRepository->getSubjectsByPageId( $targetPageId );

		// The subject -> page index permits one id on two pages. Refuse that state rather than add
		// to it: which page such a Subject belongs to is already ambiguous.
		if ( $targetSubjects->getAllSubjects()->getSubject( $subjectId ) !== null ) {
			$this->presenter->presentSubjectAlreadyOnTargetPage();
			return;
		}

		// Both pages are authorized before either is written, so a denial on the second cannot leave
		// the first already rewritten. Each call charges the edit rate limit, which is what a move
		// costs: it is two page edits.
		if ( !$this->writeAuthorizer->authorize( $sourcePageId ) || !$this->writeAuthorizer->authorize( $targetPageId ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to move this subject' );
		}

		// The source page as it stands, kept aside untouched: reading it again is how the rollback
		// below restores the page exactly, ordering included, rather than reconstructing it.
		$sourceSubjectsBeforeMove = $this->subjectRepository->getSubjectsByPageId( $sourcePageId );

		$sourceSubjects->removeSubject( $subjectId );
		$this->addToTarget( $targetSubjects, $subject, $request->makeMainSubject );

		$this->write( $request, $sourceSubjects, $sourceSubjectsBeforeMove, $sourcePageId, $targetSubjects, $targetPageId );
	}

	private function addToTarget( PageSubjects $targetSubjects, Subject $subject, bool $makeMainSubject ): void {
		if ( !$makeMainSubject ) {
			$targetSubjects->createChildSubject( $subject );
			return;
		}

		// setMainSubject drops the previous main from the page rather than demoting it, so the
		// previous main is put back as a child, as SetMainSubjectAction does.
		$previousMain = $targetSubjects->getMainSubject();
		$targetSubjects->setMainSubject( $subject );

		if ( $previousMain !== null ) {
			$targetSubjects->createChildSubject( $previousMain );
		}
	}

	private function write(
		MoveSubjectRequest $request,
		PageSubjects $sourceSubjects,
		PageSubjects $sourceSubjectsBeforeMove,
		PageId $sourcePageId,
		PageSubjects $targetSubjects,
		PageId $targetPageId
	): void {
		// Source first, for the projection reason in the class docblock: the target write has to be
		// the last word on the moved Subject's node.
		$sourceStatus = $this->subjectRepository->savePageSubjects( $sourceSubjects, $sourcePageId, $request->comment );

		if ( $sourceStatus->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentSourcePageNotFound();
			return;
		}

		$targetStatus = $this->subjectRepository->savePageSubjects( $targetSubjects, $targetPageId, $request->comment );

		if ( $targetStatus->status === PageContentSavingStatus::ERROR ) {
			$this->undoSourceWrite( $sourceSubjectsBeforeMove, $sourcePageId, $request->comment );
			return;
		}

		$this->presenter->presentMoved();
	}

	/**
	 * Writes the source page back as it was, so a move whose target write failed leaves the Subject
	 * where it started rather than on no page at all - which is a state the API cannot write to.
	 * The page's own write authorization was taken before the first write and covers this one.
	 */
	private function undoSourceWrite( PageSubjects $sourceSubjectsBeforeMove, PageId $sourcePageId, ?string $comment ): void {
		$undoStatus = $this->subjectRepository->savePageSubjects( $sourceSubjectsBeforeMove, $sourcePageId, $comment );

		if ( $undoStatus->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentMoveIncomplete();
			return;
		}

		$this->presenter->presentTargetPageNotFound();
	}

}
