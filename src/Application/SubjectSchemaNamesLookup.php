<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

interface SubjectSchemaNamesLookup {

	/**
	 * The Subjects the page publishes, as last recorded.
	 *
	 * @return array<string, string> The name of each Subject's Schema, without its Source, by Subject id
	 */
	public function getSchemaNamesOfSubjectsOn( PageId $pageId ): array;

}
