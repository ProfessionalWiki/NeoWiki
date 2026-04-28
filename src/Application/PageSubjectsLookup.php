<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

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

}
