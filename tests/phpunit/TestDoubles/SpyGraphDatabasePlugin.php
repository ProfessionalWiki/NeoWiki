<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class SpyGraphDatabasePlugin implements GraphDatabasePlugin {

	public int $initializeCount = 0;

	/** @var Page[] */
	public array $savedPages = [];

	/** @var PageId[] */
	public array $deletedPageIds = [];

	public function initialize(): void {
		$this->initializeCount++;
	}

	public function savePage( Page $page ): void {
		$this->savedPages[] = $page;
	}

	public function deletePage( PageId $pageId ): void {
		$this->deletedPageIds[] = $pageId;
	}

}
