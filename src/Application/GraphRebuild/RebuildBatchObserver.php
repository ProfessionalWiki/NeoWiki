<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Watches a rebuild advance, one batch at a time.
 *
 * Called after each batch is projected and its progress recorded, which makes it both where a caller
 * reports progress and the one point at which it may hold the rebuild up — the maintenance script waits
 * for the replicas to catch up here.
 *
 * What each batch reports is what that batch did. How much there is to do in total is not passed, because
 * only one caller wants it and counting it is a scan of the wiki: an observer that wants a denominator
 * counts one for itself, once, rather than being handed one per batch.
 */
interface RebuildBatchObserver {

	/**
	 * A page the rebuild could not reconcile, whether projecting it or removing it. The run counts these
	 * but does not keep them, so this is a caller's one chance to say which pages they were.
	 */
	public function pageFailed( int $pageId ): void;

	public function afterPageBatch( RebuildRun $run ): void;

	/**
	 * @param int $removedInBatch Pages this batch removed, not counting the batches before it: a run
	 *        continued in another process has no memory of what those removed.
	 */
	public function afterDeletionBatch( RebuildRun $run, int $removedInBatch ): void;

}
