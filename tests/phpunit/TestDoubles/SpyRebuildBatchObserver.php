<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Records the run as it stood after each batch, so a test can see how a rebuild advanced rather than
 * only where it ended up.
 */
class SpyRebuildBatchObserver implements RebuildBatchObserver {

	/**
	 * @var RebuildRun[]
	 */
	public array $pageBatches = [];

	/**
	 * @var RebuildRun[]
	 */
	public array $deletionBatches = [];

	public array $reportedPageTotals = [];

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void {
		$this->pageBatches[] = $run;
		$this->reportedPageTotals[] = $totalPages;
	}

	public function afterDeletionBatch( RebuildRun $run, int $removed, int $totalDeleted ): void {
		$this->deletionBatches[] = $run;
	}

}
