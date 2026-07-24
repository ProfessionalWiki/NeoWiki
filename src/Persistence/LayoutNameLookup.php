<?php

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\Title\TitleValue;

interface LayoutNameLookup {

	/**
	 * The Layout names the caller may read, keyed by page ID, in page-ID order, starting after the
	 * given page ID. The summaries endpoint fills its page from this iterable and uses the keys as
	 * pagination cursor, so an unreadable Layout neither appears, nor takes page space, nor is
	 * inferable from the pagination (#1062).
	 *
	 * @return iterable<int, TitleValue>
	 */
	public function getReadableLayoutNames( int $afterPageId = 0 ): iterable;

}
