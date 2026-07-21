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
	 */
	public function deleteSubject( SubjectId $id, ?string $comment ): void;

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

}
