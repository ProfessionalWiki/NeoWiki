<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\Title\Title;

interface ImportedPageTitlesLookup {

	/**
	 * The titles of the pages a prior import created, so it can tell which of them a new run no
	 * longer covers and should remove.
	 *
	 * @return Title[]
	 */
	public function getImportedPageTitles(): array;

}
