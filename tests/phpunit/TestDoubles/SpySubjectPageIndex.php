<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;

class SpySubjectPageIndex implements SubjectPageIndex {

	/**
	 * @var array<int, string[]> The Subject ids each page was indexed with, keyed by page id
	 */
	public array $indexedSubjectsByPageId = [];

	/**
	 * @var int[]
	 */
	public array $removedPageIds = [];

	/**
	 * @param string[] $subjectIds
	 */
	public function setSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		$this->indexedSubjectsByPageId[$pageId->id] = $subjectIds;
	}

	public function removePage( PageId $pageId ): void {
		$this->removedPageIds[] = $pageId->id;
	}

}
