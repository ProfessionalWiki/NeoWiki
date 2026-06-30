<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Example of an extension-contributed graph-database backend. A real plugin would project
 * the page and its subjects into its own store here; this example does nothing so it stays
 * side-effect-free in the test suite.
 */
class RedHerbGraphDatabasePlugin implements GraphDatabasePlugin {

	public function savePage( Page $page ): void {
	}

	public function deletePage( PageId $pageId ): void {
	}

}
