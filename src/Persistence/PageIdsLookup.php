<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

interface PageIdsLookup {

	/**
	 * The page id of every page on the wiki, in ascending order. Every page is projected, so this is the
	 * set the graph rebuild and the RDF dump walk.
	 *
	 * $afterPageId starts the walk past a page id already handled, so a run interrupted partway can
	 * resume instead of redoing the wiki from the beginning.
	 *
	 * @return iterable<int>
	 */
	public function getPageIds( int $afterPageId = 0 ): iterable;

	/**
	 * The next $limit page ids after $afterPageId, in ascending page id order.
	 *
	 * Bounded rather than yielded, for the graph rebuild: it records how far it got after each batch and
	 * continues there, which it cannot do part-way through a generator.
	 *
	 * @return int[]
	 */
	public function getPageIdsAfter( int $afterPageId, int $limit ): array;

	/**
	 * How many pages the wiki has, for reporting progress against.
	 */
	public function countPages(): int;

}
