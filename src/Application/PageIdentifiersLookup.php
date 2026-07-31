<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;

interface PageIdentifiersLookup {

	public function getPageIdOfSubject( SubjectId $subjectId ): ?PageIdentifiers;

	/**
	 * Keyed by Subject id text. Ids hosted by no page are absent from the map; ids beyond those
	 * requested carry no meaning and are ignored.
	 *
	 * @return array<string, PageIdentifiers>
	 */
	public function getPageIdsOfSubjects( SubjectIdList $subjectIds ): array;

}
