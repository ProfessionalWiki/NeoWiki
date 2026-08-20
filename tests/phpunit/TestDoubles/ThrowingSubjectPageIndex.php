<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;
use RuntimeException;

/**
 * Stands in for an index write that hits a database fault. Nothing isolates these, so they propagate
 * out of whatever called them.
 */
class ThrowingSubjectPageIndex implements SubjectPageIndex {

	public function setSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		throw new RuntimeException( 'index write failed' );
	}

	public function removePage( PageId $pageId ): void {
		throw new RuntimeException( 'index write failed' );
	}

}
