<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * For tests with nowhere to report progress to. The run records carry the same progress, so nothing is
 * lost by not watching.
 */
class NullRebuildBatchObserver implements RebuildBatchObserver {

	public function pageFailed( int $pageId ): void {
	}

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void {
	}

	public function afterDeletionBatch( RebuildRun $run, int $removedSoFar, int $totalDeleted ): void {
	}

}
