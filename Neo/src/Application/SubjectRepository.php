<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

interface SubjectRepository {

	public function getSubject( SubjectId $subjectId ): ?Subject;

	public function saveSubject( Subject $subject ): void;

}
