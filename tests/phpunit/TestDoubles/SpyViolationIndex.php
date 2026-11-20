<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Persistence\ViolationIndex;

class SpyViolationIndex implements ViolationIndex {

	/**
	 * @var array<int, SubjectViolation[]> The Violations each page was last recorded with, keyed by page id
	 */
	public array $violationsByPageId = [];

	/**
	 * @var int[]
	 */
	public array $removedPageIds = [];

	public function setViolationsOfPage( PageId $pageId, SubjectViolation ...$violations ): void {
		$this->violationsByPageId[$pageId->id] = $violations;
	}

	public function removePage( PageId $pageId ): void {
		$this->removedPageIds[] = $pageId->id;
	}

}
