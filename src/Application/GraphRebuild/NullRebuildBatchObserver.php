<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\GraphRebuild;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * For callers with nowhere to report progress to. The run records carry the same progress, so nothing
 * is lost by not watching.
 */
class NullRebuildBatchObserver implements RebuildBatchObserver {

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void {
	}

	public function afterDeletionBatch( RebuildRun $run, int $removed, int $totalDeleted ): void {
	}

}
