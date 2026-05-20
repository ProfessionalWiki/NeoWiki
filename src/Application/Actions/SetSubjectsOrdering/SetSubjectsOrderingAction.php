<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\SubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use RuntimeException;

readonly class SetSubjectsOrderingAction {

	public function __construct(
		private SetSubjectsOrderingPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private SubjectAuthorizer $subjectAuthorizer,
	) {
	}

	public function setOrdering( SetSubjectsOrderingRequest $request ): void {
		if ( !$this->subjectAuthorizer->canEditSubject() ) {
			throw new RuntimeException( 'You do not have the necessary permissions to change the subject ordering' );
		}

		$pageId = new PageId( $request->pageId );
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

		$this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );
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
