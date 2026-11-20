<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;

/**
 * The Violations of the Subjects each page publishes, for finding invalid Subjects across the wiki.
 */
interface ViolationIndex {

	/**
	 * Records that the page's Subjects have exactly these Violations, dropping whatever it had before.
	 */
	public function setViolationsOfPage( PageId $pageId, SubjectViolation ...$violations ): void;

	public function removePage( PageId $pageId ): void;

}
