<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

interface PageIdsLookup {

	/**
	 * The page id of every page on the wiki, in ascending order. Every page is projected, so this is the
	 * set the graph rebuild and the RDF dump walk.
	 *
	 * @return iterable<int>
	 */
	public function getPageIds(): iterable;

}
