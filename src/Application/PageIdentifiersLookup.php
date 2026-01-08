<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

interface PageIdentifiersLookup {

	public function getPageIdOfSubject( SubjectId $subjectId ): ?PageIdentifiers;

}
