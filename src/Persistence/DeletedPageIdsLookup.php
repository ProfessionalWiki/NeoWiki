<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

interface DeletedPageIdsLookup {

	/**
	 * The page id of every archived page the wiki no longer has, and which therefore should not be
	 * present in any graph database either. A page id can be yielded more than once.
	 *
	 * @return iterable<int>
	 */
	public function getDeletedPageIds(): iterable;

}
