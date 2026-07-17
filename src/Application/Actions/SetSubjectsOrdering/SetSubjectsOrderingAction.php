<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
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

		try {
			$mainSubjectId = $request->mainSubjectId === null ? null : $this->subjectIdParser->parse( $request->mainSubjectId );
			$childSubjectIds = array_map( fn ( string $id ) => $this->subjectIdParser->parse( $id ), $request->childSubjectIds );
		} catch ( InvalidArgumentException $e ) {
			$this->presenter->presentInvalidOrdering( $e->getMessage() );
			return;
		}

		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageId );

		if ( $this->matchesCurrent( $pageSubjects, $mainSubjectId, $childSubjectIds ) ) {
			$this->presenter->presentNoChange();
			return;
		}

		try {
			$pageSubjects->setOrdering( $mainSubjectId, $childSubjectIds );
		} catch ( InvalidArgumentException $e ) {
			$this->presenter->presentInvalidOrdering( $e->getMessage() );
			return;
		}

		$this->subjectRepository->savePageSubjects( $pageSubjects, $pageId, $request->comment );
		$this->presenter->presentOrderingChanged();
	}

	/**
	 * @param SubjectId[] $childSubjectIds
	 */
	private function matchesCurrent( PageSubjects $pageSubjects, ?SubjectId $mainSubjectId, array $childSubjectIds ): bool {
		$currentMainId = $pageSubjects->getMainSubject()?->id->text;
		if ( $currentMainId !== $mainSubjectId?->text ) {
			return false;
		}
		return $pageSubjects->getChildSubjects()->getIdsAsTextArray()
			=== array_map( fn ( SubjectId $id ) => $id->text, $childSubjectIds );
	}

}
