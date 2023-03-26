<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\SubjectId;

interface PageIdLookup {

	public function getPageIdOfSubject( SubjectId $subjectId ): ?int;

}
