<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Indexes nothing, for the paths that reproject a graph store rather than write a revision.
 *
 * A graph rebuild walks the wiki from a replica, so the revision it projects may already have been
 * superseded — convergence a query projection is defined by. The index is not allowed to lag that way
 * (ADR 32), so a rebuild leaves it alone; RebuildSubjectPageIndex.php repairs the index itself.
 */
class NullSubjectPageIndex implements SubjectPageIndex {

	public function setSubjectsOfPage( PageId $pageId, array $subjectHeaders ): void {
	}

	public function removePage( PageId $pageId ): void {
	}

}
