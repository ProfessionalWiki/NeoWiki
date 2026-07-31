<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

interface SubjectLookup {

	public function getSubject( SubjectId $subjectId ): ?Subject;

	/**
	 * Ids that resolve to no Subject are absent from the map, so its size is the number found.
	 */
	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap;

}
