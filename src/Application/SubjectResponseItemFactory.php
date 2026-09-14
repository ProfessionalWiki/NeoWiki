<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * Turns Subjects and the pages holding them into the response items every Subject-serving query
 * answers with, so a Subject is named the same way whichever query returned it.
 */
readonly class SubjectResponseItemFactory {

	public function __construct(
		private PageSubjectsLookup $pageSubjectsLookup,
	) {
	}

	/**
	 * @param array<string, array{Subject, ?PageIdentifiers}> $placedSubjects
	 * @return array<string, GetSubjectResponseItem>
	 */
	public function createResponseItems( array $placedSubjects, bool $includePageIdentifiers ): array {
		$mainSubjectIds = $this->getMainSubjectIds( $placedSubjects );

		$items = [];

		foreach ( $placedSubjects as $idText => [ $subject, $pageIdentifiers ] ) {
			$items[$idText] = GetSubjectResponseItem::fromSubject(
				$subject,
				// Withholding the page fields must not withhold the fallback they feed: the display
				// name is built from the identifiers fetched either way.
				$includePageIdentifiers ? $pageIdentifiers : null,
				SubjectDisplayName::labelOrPageName(
					subject: $subject,
					isMainSubject: $this->isMainSubject( $subject, $pageIdentifiers, $mainSubjectIds ),
					pageName: $pageIdentifiers?->getTitle() ?? ''
				)
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
	private function isMainSubject(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		array $mainSubjectIds
	): bool {
		$mainSubjectId = $pageIdentifiers === null ? null : $mainSubjectIds[$pageIdentifiers->getId()->id] ?? null;

		return $mainSubjectId !== null && $mainSubjectId->equals( $subject->getId() );
	}

}
