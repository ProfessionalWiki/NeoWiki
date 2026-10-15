<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;

/**
 * A page's Subjects in the revision the Subject being named was read from: the latest, the published one, or one a
 * read asked for.
 */
interface PageSubjectsAtRevisionLookup {

	/**
	 * Empty when that revision holds no Subjects.
	 */
	public function getPageSubjects( PageId $pageId ): PageSubjects;

}
