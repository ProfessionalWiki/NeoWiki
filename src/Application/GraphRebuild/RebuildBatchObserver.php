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
 * The totals are counted per batch, so a rebuild of a wiki being edited under it reports against the
 * wiki's current size rather than the size it had when the run began.
 */
interface RebuildBatchObserver {

	/**
	 * A page the rebuild could not reconcile, whether projecting it or removing it. The run counts these
	 * but does not keep them, so this is a caller's one chance to say which pages they were.
	 */
	public function pageFailed( int $pageId ): void;

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void;

	/**
	 * @param int $removedInBatch Pages this batch removed, not counting the batches before it: a run
	 *        continued in another process has no memory of what those removed.
	 */
	public function afterDeletionBatch( RebuildRun $run, int $removedInBatch, int $totalDeleted ): void;

}
