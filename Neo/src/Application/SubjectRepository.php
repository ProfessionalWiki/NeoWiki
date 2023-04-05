<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

interface SubjectRepository {

	public function getSubject( SubjectId $subjectId ): ?Subject;

	/**
	 * Saves the subject to the primary persistence.
	 */
	public function updateSubject( Subject $subject ): void;

	public function createSubject( Subject $subject, PageId $pageId ): void;

	public function deleteSubject( SubjectId $id ): void;

}
