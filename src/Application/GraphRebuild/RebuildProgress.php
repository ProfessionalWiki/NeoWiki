<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * What a rebuild batch got through, accumulated as it walks its pages.
 *
 * It is mutable where {@see RebuildRun} is not, because a batch that fails partway must still be
 * recorded with everything it did reconcile before it stopped.
 *
 * The cursor advances past every page the batch has dealt with, whether it projected, skipped, removed
 * or failed on it. Resuming is about not redoing settled work, not about retrying failures: a page that
 * failed is counted and logged, and the next run over the wiki picks it up.
 */
class RebuildProgress {

	private int $cursor;
	private int $processed;
	private int $failed;

	public function __construct( RebuildRun $run ) {
		$this->cursor = $run->cursor;
		$this->processed = $run->processed;
		$this->failed = $run->failed;
	}

	public function pageProjected( int $pageId ): void {
		$this->cursor = $pageId;
		$this->processed++;
	}

	/**
	 * The page held no Subject to project after all — it has no current revision, or its latest one
	 * dropped the subject slot. Nothing failed, and nothing was projected.
	 */
	public function pageSkipped( int $pageId ): void {
		$this->cursor = $pageId;
	}

	public function pageFailed( int $pageId ): void {
		$this->cursor = $pageId;
		$this->failed++;
	}

	/**
	 * A page MediaWiki no longer has is gone from the store too. Not counted as processed: `processed`
	 * reports the wiki's pages projected, which is what progress is measured against.
	 */
	public function pageRemoved( int $pageId ): void {
		$this->cursor = $pageId;
	}

	/**
	 * A page MediaWiki no longer has could not be removed from the store, so the store still answers
	 * queries about it. Counted with the projection failures: both are pages left unreconciled.
	 */
	public function removalFailed( int $pageId ): void {
		$this->cursor = $pageId;
		$this->failed++;
	}

	public function applyTo( RebuildRun $run ): RebuildRun {
		return $run->withProgress( cursor: $this->cursor, processed: $this->processed, failed: $this->failed );
	}

}
