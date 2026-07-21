<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

readonly class SetSubjectsOrderingAction {

	public function __construct(
		private SetSubjectsOrderingPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
	) {
	}

	public function setOrdering( SetSubjectsOrderingRequest $request ): void {
		$pageId = new PageId( $request->pageId );

		// Gate on read before write, and before any no-op short-circuit below: a page the caller may
		// not read, and a page that does not exist, both answer the same not-found shape, so a hidden
		// page cannot be told apart from an absent one by sweeping page ids.
		if ( !$this->readAuthorizer->authorizeReadByPageId( $pageId ) ) {
			$this->presenter->presentPageNotFound();
			return;
		}

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to change the subject ordering' );
		}

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageId );

		if ( $this->matchesCurrent( $pageSubjects, $request ) ) {
			$this->presenter->presentNoChange();
			return;
		}

		try {
			$pageSubjects->setOrdering(
				$request->mainSubjectId === null ? null : new SubjectId( $request->mainSubjectId ),
				array_map( fn ( string $id ) => new SubjectId( $id ), $request->childSubjectIds )
			);
		} catch ( InvalidArgumentException $e ) {
			$this->presenter->presentInvalidOrdering( $e->getMessage() );
			return;
		}

		$status = $this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );

		// The read gate above already turns an unresolvable page away; this catches the page going
		// away between that check and the save, so a dropped write is never reported as changed.
		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentPageNotFound();
			return;
		}

		$this->presenter->presentOrderingChanged();
	}

	private function matchesCurrent( PageSubjects $pageSubjects, SetSubjectsOrderingRequest $request ): bool {
		$currentMainId = $pageSubjects->getMainSubject()?->id->text;
		if ( $currentMainId !== $request->mainSubjectId ) {
			return false;
		}
		return $pageSubjects->getChildSubjects()->getIdsAsTextArray() === $request->childSubjectIds;
	}

}
