<?php

namespace ProfessionalWiki\NeoWiki\Persistence;

use MediaWiki\Title\TitleValue;

interface SchemaNameLookup {

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit, int $offset = 0 ): array;

	/**
	 * The Schema names the caller may read, keyed by database key, in name order, starting after
	 * the given database key. With a search, only the names that contain it in any case, its spaces
	 * and underscores alike. The summaries endpoint fills its page from this iterable and uses the
	 * keys as pagination cursor, so an unreadable Schema neither appears, nor takes page space, nor
	 * is inferable from the pagination (#1062).
	 *
	 * @return iterable<string, TitleValue>
	 */
	public function getReadableSchemaNames( string $search, string $afterName ): iterable;

}
