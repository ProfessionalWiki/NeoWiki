<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;

interface SubjectRepository extends SubjectLookup {

	/**
	 * Does nothing if the subject is not found.
	 * TODO: throw exception on not found?
	 * TODO: document exceptions
	 */
	public function updateSubject( Subject $subject, ?string $comment = null ): void;

	/**
	 * TODO: document exceptions
	 *
	 * Answers REVISION_CREATED only when the Subject was actually removed. The index can name a page
	 * whose slot no longer holds it, and the page can go away under the write; both answer
	 * NO_CHANGES or ERROR rather than reporting a deletion that did not happen.
	 */
	public function deleteSubject( SubjectId $id, ?string $comment ): PageContentSavingStatus;

	/**
	 * TODO: document exceptions
	 */
	public function getSubjectsByPageId( PageId $pageId ): PageSubjects;

	/**
	 * Returns the outcome of the save. A PageContentSavingStatus::ERROR means the write did not land
	 * - most notably when the target page no longer resolves - so callers can avoid reporting success
	 * for a write that was silently dropped.
	 *
	 * TODO: document exceptions
	 */
	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment = null ): PageContentSavingStatus;

	/**
	 * Creates the page $pageTitle names and puts $pageSubjects on it, in one revision. The page a
	 * Subject gets to itself carries no wikitext: what it says about the Subject is rendered from
	 * the Subject slot.
	 *
	 * A page that already exists is refused rather than edited, so a page that appeared since the
	 * caller last looked - which is what reading a replica leaves room for - cannot silently
	 * receive the Subject. The status is ERROR then, as it is for any write that did not land.
	 */
	public function createPageWithSubjects( string $pageTitle, PageSubjects $pageSubjects, ?string $comment = null ): PageContentSavingStatus;

}
