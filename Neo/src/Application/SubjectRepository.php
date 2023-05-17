<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

interface SubjectRepository extends SubjectLookup {

	/**
	 * Does nothing if the subject is not found.
	 * TODO: throw exception on not found?
	 * TODO: document exceptions
	 */
	public function updateSubject( Subject $subject ): void;

	/**
	 * TODO: document exceptions
	 */
	public function deleteSubject( SubjectId $id ): void;

	/**
	 * TODO: document exceptions
	 */
	public function getSubjectsByPageId( PageId $pageId ): PageSubjects;

	/**
	 * TODO: document exceptions
	 */
	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId ): void;

}
