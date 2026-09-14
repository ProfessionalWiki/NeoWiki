<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;

readonly class GetSubjectQuery {

	public function __construct(
		private GetSubjectPresenter $presenter,
		private SubjectLookup $subjectLookup,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private SubjectResponseItemFactory $responseItemFactory,
		private PageReadAuthorizer $readAuthorizer,
		private SubjectIdParser $subjectIdParser,
	) {
	}

	public function execute(
		string $subjectId,
		bool $includePageIdentifiers,
		bool $includeReferencedSubjects
	): void {
		$requestedId = $this->subjectIdParser->parseOrThrow( $subjectId );
		$subject = $this->subjectLookup->getSubject( $requestedId );

		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$pageIdentifiers = $this->hostingPage( $requestedId );

		if ( !$this->mayServe( $requestedId, $pageIdentifiers ) ) {
			// Denial takes exactly the absent-Subject path, so harvested Subject ids cannot
			// be confirmed to exist on restricted pages (#1046).
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$placedSubjects = [ $subject->getId()->text => [ $subject, $pageIdentifiers ] ];

		if ( $includeReferencedSubjects ) {
			$referencedIds = $subject->getReferencedSubjects();

			$referencedSubjects = $this->subjectLookup->getSubjects( $referencedIds );
			$hostingPages = $this->pageIdentifiersLookup->getPageIdsOfSubjects( $referencedIds );

			// Iterated by the requested ids, not the returned map: the response keeps relation
			// order, and SubjectMap promises no order of its own.
			foreach ( $referencedIds->asArray() as $idText => $id ) {
				$referencedSubject = $referencedSubjects->getSubject( $id );

				if ( $referencedSubject === null ) {
					continue;
				}

				$referencedPage = $hostingPages[$idText] ?? null;

				if ( !$this->mayServe( $id, $referencedPage ) ) {
					continue;
				}

				$placedSubjects[$idText] = [ $referencedSubject, $referencedPage ];
			}
		}

		$this->presenter->presentSubject(
			new GetSubjectResponse(
				requestedId: $subject->getId()->text,
				subjects: $this->responseItemFactory->createResponseItems( $placedSubjects, $includePageIdentifiers )
			)
		);
	}

	/**
	 * The page a Subject lives on, which only a Subject of this wiki has: the subject-to-page index
	 * holds local ids alone (ADR 32), so a sourced Subject is not looked up in it.
	 */
	private function hostingPage( SubjectId $id ): ?PageIdentifiers {
		return $id->isLocal() ? $this->pageIdentifiersLookup->getPageIdOfSubject( $id ) : null;
	}

	/**
	 * A local Subject is served only to a caller who may read its page (#1046). An unresolved page is
	 * allowed because it means the Subject came from the revision the caller supplied, whose page
	 * GetSubjectApi already authorized: reads through the graph-backed repository always resolve the
	 * owning page. Denying would hide Subjects from readable old revisions after the Subject was later
	 * deleted.
	 *
	 * A sourced Subject is served as its Source vouched it (ADR 23): there is no page of this wiki to
	 * authorize against, and NeoWiki performs no per-user authorization on sourced data.
	 */
	private function mayServe( SubjectId $id, ?PageIdentifiers $pageIdentifiers ): bool {
		if ( !$id->isLocal() ) {
			return true;
		}

		return $pageIdentifiers === null
			|| $this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() );
	}

}
