<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;

/**
 * Answers what a page id identifies, from the wiki itself rather than from the graph projection.
 * Callers that hold a page id use this instead of {@see PageIdentifiersLookup}: it needs no graph
 * round trip, and it answers for a page written moments ago, which a graph read replica may not.
 */
interface PageIdentifiersResolver {

	/**
	 * Null when no page carries this id.
	 */
	public function getIdentifiersOfPage( PageId $pageId ): ?PageIdentifiers;

}
