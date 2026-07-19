<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

interface DeletedSubjectPageIdsLookup {

	/**
	 * Page ids that once carried Subjects and no longer exist in MediaWiki, and so should not be
	 * present in any graph database either.
	 *
	 * @return int[]
	 */
	public function getDeletedSubjectPageIds(): array;

}
