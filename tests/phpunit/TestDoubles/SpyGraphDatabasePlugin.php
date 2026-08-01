<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use RuntimeException;
use Throwable;

/**
 * Records what reaches a graph store. It accepts everything unless told otherwise: name the pages it
 * refuses to save, or tell it to refuse deletions, to stand for a store that chokes on part of what a
 * rebuild sends it.
 *
 * What it throws when it refuses is configurable, because a rebuild reads the kind of failure: a store
 * rejecting one page costs that page, while a wiki-database error ends the run.
 */
class SpyGraphDatabasePlugin implements GraphDatabasePlugin {

	public const string FAILURE_MESSAGE = 'this page is not acceptable';

	public int $initializeCount = 0;

	/**
	 * @var Page[]
	 */
	public array $savedPages = [];

	/**
	 * @var PageId[]
	 */
	public array $deletedPageIds = [];

	/**
	 * @param int[] $refusedPageIds Pages this store will not save. Every other page is saved and recorded.
	 * @param bool $refusesDeletions Whether it also refuses to let go of the pages the wiki has deleted.
	 * @param Throwable|null $failure What it throws when it refuses, defaulting to the kind of error a
	 *        store raises about a page it cannot accept.
	 */
	public function __construct(
		private readonly array $refusedPageIds = [],
		private readonly bool $refusesDeletions = false,
		private readonly ?Throwable $failure = null,
	) {
	}

	public function initialize(): void {
		$this->initializeCount++;
	}

	public function savePage( Page $page ): void {
		if ( in_array( $page->getId()->id, $this->refusedPageIds, true ) ) {
			throw $this->refusal();
		}

		$this->savedPages[] = $page;
	}

	public function deletePage( PageId $pageId ): void {
		if ( $this->refusesDeletions ) {
			throw $this->refusal();
		}

		$this->deletedPageIds[] = $pageId;
	}

	private function refusal(): Throwable {
		return $this->failure ?? new RuntimeException( self::FAILURE_MESSAGE );
	}

}
