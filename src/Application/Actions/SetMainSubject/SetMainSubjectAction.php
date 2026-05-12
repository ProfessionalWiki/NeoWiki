<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject;

use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SetMainSubjectAction {

	public function __construct(
		private SetMainSubjectPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectAuthorizer,
	) {
	}

	public function setMainSubject( SetMainSubjectRequest $request ): void {
		if ( !$this->subjectAuthorizer->canEditSubject() ) {
			throw new \RuntimeException( 'You do not have the necessary permissions to change the main subject' );
		}

		$pageId = new PageId( $request->pageId );
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

		$this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $comment );
		$this->presenter->presentMainSubjectChanged();
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

		$this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $comment );
		$this->presenter->presentMainSubjectChanged();
	}

}
