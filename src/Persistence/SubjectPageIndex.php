<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * The write side of the subject -> page index that {@see \ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup}
 * reads (ADR 32).
 */
interface SubjectPageIndex {

	/**
	 * Records that the page holds exactly these Subjects, dropping whatever it held before. Idempotent,
	 * so the same page can be indexed as often as it is written.
	 *
	 * @param string[] $subjectIds Without duplicates: a page holds each of its Subjects once.
	 */
	public function setSubjectsOfPage( PageId $pageId, array $subjectIds ): void;

	public function removePage( PageId $pageId ): void;

}
