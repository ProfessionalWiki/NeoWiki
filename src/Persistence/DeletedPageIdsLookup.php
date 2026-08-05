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

	/**
	 * The next $limit deleted page ids after $afterPageId, in ascending page id order, without repeats.
	 *
	 * Ordered by page id rather than by archive id, which is what {@see self::getDeletedPageIds()} pages
	 * by: the graph rebuild records how far through this phase it got and continues there, and an archive
	 * id is not a position a page-id cursor can resume from. The archive has no index on ar_page_id, so
	 * this costs a sort per batch — a cost the rebuild pays for being resumable, and which the unbounded
	 * reader above deliberately does not.
	 *
	 * @return int[]
	 */
	public function getDeletedPageIdsAfter( int $afterPageId, int $limit ): array;

	/**
	 * How many pages the wiki no longer has, for reporting progress against.
	 */
	public function countDeletedPages(): int;

}
