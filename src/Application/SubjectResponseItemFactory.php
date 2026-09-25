<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;

/**
 * Turns Subjects and the pages holding them into the response items every Subject-serving query
 * answers with, so a Subject is named the same way whichever query returned it.
 */
readonly class SubjectResponseItemFactory {

	public function __construct(
		private PageSubjectsLookup $pageSubjectsLookup,
		private SubjectNamer $subjectNamer,
	) {
	}

	/**
	 * @param array<string, array{Subject, ?PageIdentifiers}> $placedSubjects
	 * @return array<string, GetSubjectResponseItem>
	 */
	public function createResponseItems( array $placedSubjects, bool $includePageIdentifiers ): array {
		$items = [];
		$pagesRead = [];

		foreach ( $placedSubjects as $idText => [ $subject, $pageIdentifiers ] ) {
			$items[$idText] = GetSubjectResponseItem::fromSubject(
				$subject,
				// Withholding the page fields must not withhold the fallback they feed: the display
				// name is built from the identifiers fetched either way.
				$includePageIdentifiers ? $pageIdentifiers : null,
				$this->chosenName( $subject, $pageIdentifiers, $pagesRead )
			);
		}

		return $items;
	}

	/**
	 * A page is read only for a Subject that neither a stored label nor its Schema's label template
	 * names: either is the chosen name whichever page holds the Subject, and a Subject without a page
	 * of this wiki has no page name to fall back on. One read per distinct page, however many of the
	 * response's Subjects live on it.
	 *
	 * @param array<int, PageSubjects> $pagesRead Page ID → the Subjects that page holds, filled as
	 *   pages are reached.
	 */
	private function chosenName( Subject $subject, ?PageIdentifiers $pageIdentifiers, array &$pagesRead ): ?string {
		$ownLabel = $this->subjectNamer->ownLabel( $subject );

		if ( $ownLabel !== null || $pageIdentifiers === null ) {
			return $ownLabel;
		}

		$pageId = $pageIdentifiers->getId();
		$pagesRead[$pageId->id] ??= $this->pageSubjectsLookup->getPageSubjects( $pageId );

		// No own label means the template gave none either.
		return SubjectDisplayName::chosenName( $subject, null, $pagesRead[$pageId->id], $pageIdentifiers->getTitle() );
	}

}
