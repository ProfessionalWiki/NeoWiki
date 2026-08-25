<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

readonly class SetSubjectsOrderingAction {

	public function __construct(
		private SetSubjectsOrderingPresenter $presenter,
		private SubjectRepository $subjectRepository,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private SubjectIdParser $subjectIdParser,
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
				$request->mainSubjectId === null ? null : $this->subjectIdParser->parseOrThrow( $request->mainSubjectId ),
				array_map( fn ( string $id ) => $this->subjectIdParser->parseOrThrow( $id ), $request->childSubjectIds )
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

	/**
	 * Ids are parsed before being compared, so asking for the ordering the page already has is a no-op
	 * however the ids are spelled: an explicitly-local id names the same Subject as its bare form. A
	 * malformed id compares unequal and falls through to setOrdering(), which reports it.
	 */
	private function matchesCurrent( PageSubjects $pageSubjects, SetSubjectsOrderingRequest $request ): bool {
		if ( $pageSubjects->getMainSubject()?->id->text !== $this->canonicalize( $request->mainSubjectId ) ) {
			return false;
		}

		return $pageSubjects->getChildSubjects()->getIdsAsTextArray()
			=== array_map( $this->canonicalize( ... ), $request->childSubjectIds );
	}

	/**
	 * The id's canonical text, or the text as given when it is not a well-formed id — which no stored
	 * id can equal, so a malformed request never short-circuits as a no-op.
	 */
	private function canonicalize( ?string $id ): ?string {
		return $id === null ? null : ( $this->subjectIdParser->parse( $id )?->text ?? $id );
	}

}
