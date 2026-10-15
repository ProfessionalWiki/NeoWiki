<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;

/**
 * The Subjects each page publishes and their relations, for NeoWiki's Subject lists, suggestions, referencing
 * lists and counts. Unlike {@see SubjectPageIndex}, which follows a page's latest revision, this follows the
 * revision its RevisionPolicy publishes.
 */
interface PublishedSubjectIndex {

	/**
	 * Records that the page publishes exactly these Subjects, dropping whatever it published before.
	 *
	 * @param string $pageName The page's prefixed title text, which a Main Subject without a label is named by
	 * @param string $publishedTimestamp When the published revision was saved, as MediaWiki writes timestamps
	 */
	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void;

	public function removePage( PageId $pageId ): void;

}
