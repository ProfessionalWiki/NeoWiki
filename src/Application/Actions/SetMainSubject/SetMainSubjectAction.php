<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject;

use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

readonly class SetMainSubjectAction {

	public function __construct(
		private SetMainSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
	) {
	}

	public function setMainSubject( SetMainSubjectRequest $request ): void {
		$pageId = new PageId( $request->pageId );

		// Gate on read before write, and before any no-op short-circuit below: a page the caller may
		// not read, and a page that does not exist, both answer the same not-found shape, so a hidden
		// page cannot be told apart from an absent one by sweeping page ids.
		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageId ) ) {
			$this->presenter->presentPageNotFound();
			return;
		}

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to change the main subject' );
		}

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageId );
		$previousMain = $pageSubjects->getMainSubject();

		if ( $request->subjectId === null ) {
			$this->clearMain( $pageSubjects, $previousMain, $pageId, $request->comment );
			return;
		}

		$this->promoteToMain( $pageSubjects, $previousMain, new SubjectId( $request->subjectId ), $pageId, $request->comment );
	}

	private function clearMain( PageSubjects $pageSubjects, ?Subject $previousMain, PageId $pageId, ?string $comment ): void {
		if ( $previousMain === null ) {
			$this->presenter->presentNoChange();
			return;
		}

		$pageSubjects->removeSubject( $previousMain->id );
		$pageSubjects->createChildSubject( $previousMain );

		$this->saveAndPresentChanged( $pageSubjects, $pageId, $comment );
	}

	private function promoteToMain(
		PageSubjects $pageSubjects,
		?Subject $previousMain,
		SubjectId $newMainId,
		PageId $pageId,
		?string $comment
	): void {
		if ( $previousMain !== null && $previousMain->id->equals( $newMainId ) ) {
			$this->presenter->presentNoChange();
			return;
		}

		$newMain = $pageSubjects->getAllSubjects()->getSubject( $newMainId );

		if ( $newMain === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$pageSubjects->removeSubject( $newMainId );
		$pageSubjects->setMainSubject( $newMain );

		if ( $previousMain !== null ) {
			$pageSubjects->createChildSubject( $previousMain );
		}

		$this->saveAndPresentChanged( $pageSubjects, $pageId, $comment );
	}

	private function saveAndPresentChanged( PageSubjects $pageSubjects, PageId $pageId, ?string $comment ): void {
		$status = $this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $comment );

		// The read gate at the top already turns an unresolvable page away; this catches the page
		// going away between that check and the save, so a dropped write is never reported as changed.
		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentPageNotFound();
			return;
		}

		$this->presenter->presentMainSubjectChanged();
	}

}
