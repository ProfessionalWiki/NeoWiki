<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

interface DeletedPageIdsLookup {

	/**
	 * The next $limit deleted page ids after $afterPageId, in ascending page id order, without repeats.
	 *
	 * Ordered by page id, which is what the rebuild's cursor holds. The archive has no index on
	 * ar_page_id, so this costs a sort per batch — the price of a walk that can record how far it got
	 * and continue there.
	 *
	 * @return int[]
	 */
	public function getDeletedPageIdsAfter( int $afterPageId, int $limit ): array;

	/**
	 * How many pages the wiki no longer has, for reporting progress against.
	 */
	public function countDeletedPages(): int;

}
