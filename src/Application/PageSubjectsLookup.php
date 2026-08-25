<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class PageSubjectsLookup {

	public function __construct(
		private readonly SubjectRepository $subjectRepository,
	) {
	}

	public function pageHasSubjects( PageId $pageId ): bool {
		return $this->subjectRepository->getSubjectsByPageId( $pageId )->hasSubjects();
	}

	public function pageHasMainSubject( PageId $pageId ): bool {
		return $this->subjectRepository->getSubjectsByPageId( $pageId )->hasMainSubject();
	}

	/**
	 * Which of a page's Subjects is its Main Subject, which is what a display name falling back to
	 * the page name depends on. Null when the page has none, and when it has no Subjects at all.
	 */
	public function getMainSubjectId( PageId $pageId ): ?SubjectId {
		return $this->subjectRepository->getSubjectsByPageId( $pageId )->getMainSubject()?->getId();
	}

}
