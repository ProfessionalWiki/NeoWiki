<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Search;

use SearchMySQL;

/**
 * MediaWiki's MySQL search engine, indexing a page's Subjects along with its text.
 */
class SearchMySQLWithSubjects extends SearchMySQL {

	use IndexesSubjectText;

}
