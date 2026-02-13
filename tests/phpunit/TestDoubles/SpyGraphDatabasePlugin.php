<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\GraphDatabasePlugin;

class SpyGraphDatabasePlugin implements GraphDatabasePlugin {

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
