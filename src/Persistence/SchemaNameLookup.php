<?php

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\Title\TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit, int $offset = 0 ): array;

	/**
	 * The Schema names the caller may read, keyed by page ID, in page-ID order, starting after the
	 * given page ID. The summaries endpoint fills its page from this iterable and uses the keys as
	 * pagination cursor, so an unreadable Schema neither appears, nor takes page space, nor is
	 * inferable from the pagination (#1062).
	 *
	 * @return iterable<int, TitleValue>
	 */
	public function getReadableSchemaNames( int $afterPageId = 0 ): iterable;

}
