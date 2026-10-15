<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\PublishedSubjectIndex;
use RuntimeException;

/**
 * Stands in for an index write that hits a database fault.
 */
class ThrowingPublishedSubjectIndex implements PublishedSubjectIndex {

	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void {
		throw new RuntimeException( 'index write failed' );
	}

	public function removePage( PageId $pageId ): void {
		throw new RuntimeException( 'index write failed' );
	}

}
