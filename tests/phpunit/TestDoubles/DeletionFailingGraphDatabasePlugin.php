<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use RuntimeException;

/**
 * Stands in for a store that projects pages happily but will not let go of the ones the wiki has
 * deleted, the way one does when a delete hits a constraint the write path does not.
 */
class DeletionFailingGraphDatabasePlugin implements GraphDatabasePlugin {

	public const string FAILURE_MESSAGE = 'this page will not be removed';

	/**
	 * @var Page[]
	 */
	public array $savedPages = [];

	public function initialize(): void {
	}

	public function savePage( Page $page ): void {
		$this->savedPages[] = $page;
	}

	public function deletePage( PageId $pageId ): void {
		throw new RuntimeException( self::FAILURE_MESSAGE );
	}

}
