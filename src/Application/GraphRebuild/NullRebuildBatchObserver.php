<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Watches nothing. What a background rebuild uses: its progress is read from the run records rather than
 * reported as it happens, and the job runner already waits for the replicas between batches.
 */
class NullRebuildBatchObserver implements RebuildBatchObserver {

	public function pageFailed( int $pageId ): void {
	}

	public function afterPageBatch( RebuildRun $run, Closure $totalPages ): void {
	}

	public function afterDeletionBatch( RebuildRun $run, int $removedInBatch, Closure $totalDeleted ): void {
	}

}
