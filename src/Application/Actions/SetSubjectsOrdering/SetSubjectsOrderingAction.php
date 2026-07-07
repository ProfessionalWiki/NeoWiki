<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use RuntimeException;

readonly class SetSubjectsOrderingAction {

	public function __construct(
		private SetSubjectsOrderingPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private SubjectIdParser $subjectIdParser,
	) {
	}

	public function setOrdering( SetSubjectsOrderingRequest $request ): void {
		$pageId = new PageId( $request->pageId );

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
				$request->mainSubjectId === null ? null : $this->subjectIdParser->parse( $request->mainSubjectId ),
				array_map( fn ( string $id ) => $this->subjectIdParser->parse( $id ), $request->childSubjectIds )
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
