<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class GetSubjectQuery {

	public function __construct(
		private GetSubjectPresenter $presenter,
		private SubjectLookup $subjectLookup,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private PageSubjectsLookup $pageSubjectsLookup,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function execute(
		string $subjectId,
		bool $includePageIdentifiers,
		bool $includeReferencedSubjects
	): void {
		$subject = $this->subjectLookup->getSubject( new SubjectId( $subjectId ) ); // TODO: error handling on invalid ID

		if ( $subject === null ) {
			$this->presenter->presentSubjectNotFound();
			return;
		}

		$pageIdentifiers = $this->pageIdentifiersLookup->getPageIdOfSubject( $subject->id );

		if ( !$this->pageIsReadableOrUnresolved( $pageIdentifiers ) ) {
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

				if ( !$this->pageIsReadableOrUnresolved( $referencedPage ) ) {
					continue;
				}

				$placedSubjects[$idText] = [ $referencedSubject, $referencedPage ];
			}
		}

		$this->presenter->presentSubject(
			new GetSubjectResponse(
				requestedId: $subject->getId()->text,
				subjects: $this->createResponses( $placedSubjects, $includePageIdentifiers )
			)
		);
	}

	/**
	 * Unresolved is allowed because it means the Subject came from the revision the caller
	 * supplied, whose page GetSubjectApi already authorized: reads through the graph-backed
	 * repository always resolve the owning page. Denying would hide Subjects from readable old
	 * revisions after the Subject was later deleted.
	 */
	private function pageIsReadableOrUnresolved( ?PageIdentifiers $pageIdentifiers ): bool {
		return $pageIdentifiers === null || $this->readAuthorizer->authorizeReadByPageId( $pageIdentifiers->getId() );
	}

	/**
	 * @param array<string, array{Subject, ?PageIdentifiers}> $placedSubjects
	 * @return array<string, GetSubjectResponseItem>
	 */
	private function createResponses( array $placedSubjects, bool $includePageIdentifiers ): array {
		$mainSubjectIds = $this->getMainSubjectIds( $placedSubjects );

		$items = [];

		foreach ( $placedSubjects as $idText => [ $subject, $pageIdentifiers ] ) {
			$items[$idText] = GetSubjectResponseItem::fromSubject(
				$subject,
				// Withholding the page fields must not withhold the fallback they feed: the display
				// name is built from the identifiers fetched either way.
				$includePageIdentifiers ? $pageIdentifiers : null,
				$this->getDisplayName( $subject, $pageIdentifiers, $mainSubjectIds )
			);
		}

		return $items;
	}

	/**
	 * One lookup per distinct hosting page, however many of the response's Subjects live on it, and
	 * none for a page whose Subjects in the response all have a stored label.
	 *
	 * @param array<string, array{Subject, ?PageIdentifiers}> $placedSubjects
	 * @return array<int, ?SubjectId> Page ID → that page's Main Subject
	 */
	private function getMainSubjectIds( array $placedSubjects ): array {
		$mainSubjectIds = [];

		foreach ( $placedSubjects as [ $subject, $pageIdentifiers ] ) {
			if ( $pageIdentifiers === null || $subject->getLabel() !== null ) {
				continue;
			}

			$pageId = $pageIdentifiers->getId();

			if ( !array_key_exists( $pageId->id, $mainSubjectIds ) ) {
				$mainSubjectIds[$pageId->id] = $this->pageSubjectsLookup->getMainSubjectId( $pageId );
			}
		}

		return $mainSubjectIds;
	}

	/**
	 * @param array<int, ?SubjectId> $mainSubjectIds
	 */
	private function getDisplayName(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		array $mainSubjectIds
	): string {
		$mainSubjectId = $pageIdentifiers === null ? null : $mainSubjectIds[$pageIdentifiers->getId()->id] ?? null;

		return SubjectDisplayName::forSubject(
			label: $subject->getLabel(),
			isMainSubject: $mainSubjectId !== null && $mainSubjectId->equals( $subject->getId() ),
			pageName: $pageIdentifiers?->getTitle() ?? '',
			schemaName: $subject->getSchemaName()
		);
	}

}
