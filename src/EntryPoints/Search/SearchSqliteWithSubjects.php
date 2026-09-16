<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Search;

use SearchSqlite;

/**
 * MediaWiki's SQLite search engine, indexing a page's Subjects along with its text.
 */
class SearchSqliteWithSubjects extends SearchSqlite {

	use IndexesSubjectText;

}
