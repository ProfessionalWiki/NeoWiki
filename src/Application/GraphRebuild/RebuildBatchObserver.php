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
 */
interface RebuildBatchObserver {

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void;

	public function afterDeletionBatch( RebuildRun $run, int $removed, int $totalDeleted ): void;

}
