<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;

/**
 * Answers what a page id identifies: the page's current title and namespace. For callers that hold a
 * page id, where {@see PageIdentifiersLookup} is for callers that hold a Subject id.
 */
interface PageIdentifiersResolver {

	/**
	 * Null when no page carries this id.
	 */
	public function getIdentifiersOfPage( PageId $pageId ): ?PageIdentifiers;

}
