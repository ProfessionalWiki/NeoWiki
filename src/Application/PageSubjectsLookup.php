<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;

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
	 * The Subjects a page holds and which of them is its Main Subject: what a display name falling
	 * back to the page name depends on. Empty when the page has none.
	 */
	public function getPageSubjects( PageId $pageId ): PageSubjects {
		return $this->subjectRepository->getSubjectsByPageId( $pageId );
	}

}
