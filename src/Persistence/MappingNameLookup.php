<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;

interface MappingNameLookup {

	/**
	 * The names of every Mapping page, used to enumerate all Mappings. Unfiltered: the RDF-projection
	 * and DumpRdf path runs in a system context and must see every Mapping.
	 *
	 * @return MappingName[]
	 */
	public function getMappingNames(): array;

	/**
	 * The Mapping names the caller may read, keyed by page ID, in page-ID order, starting after the
	 * given page ID. The summaries endpoint fills its page from this iterable and uses the keys as
	 * pagination cursor, so an unreadable Mapping neither appears, nor takes page space, nor is
	 * inferable from the pagination (#1062).
	 *
	 * @return iterable<int, MappingName>
	 */
	public function getReadableMappingNames( int $afterPageId = 0 ): iterable;

}
