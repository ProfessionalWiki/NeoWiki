<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use RuntimeException;

/**
 * Stands in for a reachable graph backend that chokes on particular pages, the way a store does when
 * one page's data is more than it will accept. Every other page projects normally and is recorded.
 */
class SelectivelyFailingGraphDatabasePlugin implements GraphDatabasePlugin {

	public const string FAILURE_MESSAGE = 'this page is not acceptable';

	/**
	 * @var Page[]
	 */
	public array $savedPages = [];

	/**
	 * @var int[]
	 */
	private array $failingPageIds;

	public function __construct( int ...$failingPageIds ) {
		$this->failingPageIds = $failingPageIds;
	}

	public function initialize(): void {
	}

	public function savePage( Page $page ): void {
		if ( in_array( $page->getId()->id, $this->failingPageIds, true ) ) {
			throw new RuntimeException( self::FAILURE_MESSAGE );
		}

		$this->savedPages[] = $page;
	}

	public function deletePage( PageId $pageId ): void {
	}

}
