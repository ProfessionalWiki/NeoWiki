<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

/**
 * How many Subjects each Schema has on this wiki. Subjects on pages the caller may not read count too.
 */
interface SubjectCountLookup {

	/**
	 * @return array<string, int> Keyed by Schema name. A Schema without Subjects is absent.
	 */
	public function getSubjectCountsBySchema(): array;

}
