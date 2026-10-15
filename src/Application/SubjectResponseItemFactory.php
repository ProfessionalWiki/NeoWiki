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
		private PageSubjectsAtRevisionLookup $pageSubjectsLookup,
		private HostSubjectBuilder $hostSubjectBuilder,
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
			$items[$idText] = $this->newResponseItem(
				$subject,
				// Withholding the page fields must not withhold the fallback they feed: the display
				// name is built from the identifiers fetched either way.
				$includePageIdentifiers ? $pageIdentifiers : null,
				$this->pageSubjectsHolding( $subject, $pageIdentifiers, $pagesRead ),
				$pageIdentifiers?->getTitle() ?? ''
			);
		}

		return $items;
	}

	public function createResponseItem(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		PageSubjects $pageSubjects
	): GetSubjectResponseItem {
		return $this->newResponseItem( $subject, $pageIdentifiers, $pageSubjects, $pageIdentifiers?->getTitle() ?? '' );
	}

	/**
	 * @param ?PageSubjects $pageSubjects Null for a Subject whose name its page has no say in.
	 */
	private function newResponseItem(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		?PageSubjects $pageSubjects,
		string $pageName
	): GetSubjectResponseItem {
		$chosenName = $pageSubjects === null
			? $subject->getLabel()?->text
			: SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageName );

		return GetSubjectResponseItem::fromSubject(
			$subject,
			$pageIdentifiers,
			$chosenName,
			$chosenName === null && $pageSubjects !== null
				? $this->hostSubjectBuilder->build( $subject, $pageSubjects, $pageName )
				: null
		);
	}

	/**
	 * A page is read only for a label-less Subject it holds: a stored label is the chosen name whichever
	 * page holds it, and a Subject without a page of this wiki has no page name to fall back on. One
	 * read per distinct page, however many of the response's Subjects live on it.
	 *
	 * @param array<int, PageSubjects> $pagesRead Page ID → the Subjects that page holds, filled as
	 *   pages are reached.
	 */
	private function pageSubjectsHolding( Subject $subject, ?PageIdentifiers $pageIdentifiers, array &$pagesRead ): ?PageSubjects {
		if ( $subject->getLabel() !== null || $pageIdentifiers === null ) {
			return null;
		}

		$pageId = $pageIdentifiers->getId();
		$pagesRead[$pageId->id] ??= $this->pageSubjectsLookup->getPageSubjects( $pageId );

		return $pagesRead[$pageId->id];
	}

}
