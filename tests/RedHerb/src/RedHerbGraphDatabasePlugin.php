<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Example of an extension-contributed graph-database backend. A real plugin would project the
 * page and its subjects into its own store here; this example just records what it received so
 * tests can verify the registry dispatches page events to extension-registered plugins.
 */
class RedHerbGraphDatabasePlugin implements GraphDatabasePlugin {

	/** @var Page[] */
	public array $savedPages = [];

	/** @var PageId[] */
	public array $deletedPageIds = [];

	public function savePage( Page $page ): void {
		$this->savedPages[] = $page;
	}

	public function deletePage( PageId $pageId ): void {
		$this->deletedPageIds[] = $pageId;
	}

}
