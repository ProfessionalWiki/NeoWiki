<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;

class SpySubjectPageIndex implements SubjectPageIndex {

	/**
	 * @var array<int, SubjectHeader[]> The Subjects each page was indexed with, keyed by page id
	 */
	public array $indexedSubjectsByPageId = [];

	/**
	 * @var int[]
	 */
	public array $removedPageIds = [];

	/**
	 * @param SubjectHeader[] $subjectHeaders
	 */
	public function setSubjectsOfPage( PageId $pageId, array $subjectHeaders ): void {
		$this->indexedSubjectsByPageId[$pageId->id] = $subjectHeaders;
	}

	public function removePage( PageId $pageId ): void {
		$this->removedPageIds[] = $pageId->id;
	}

}
