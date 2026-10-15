<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;

/**
 * For the paths that reproject a graph store: a graph rebuild reads replicas, so what it reprojects may be
 * superseded already, which the index must not inherit.
 */
class NullPublishedSubjectIndex implements PublishedSubjectIndex {

	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void {
	}

	public function removePage( PageId $pageId ): void {
	}

}
