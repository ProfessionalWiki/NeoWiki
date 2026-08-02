<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Cancels the store's rebuild after its first batch, standing in for someone pressing cancel while a
 * rebuild is under way. Doing it from the observer is what puts the cancellation between two batches of
 * a rebuild that is running here and now, which is otherwise not reachable from inside one test.
 */
class CancellingRebuildBatchObserver implements RebuildBatchObserver {

	private bool $cancelled = false;

	public function __construct(
		private readonly GraphRebuildCoordinator $coordinator,
		private readonly string $storeName,
	) {
	}

	public function pageFailed( int $pageId ): void {
	}

	public function afterPageBatch( RebuildRun $run, int $totalPages ): void {
		if ( !$this->cancelled ) {
			$this->cancelled = true;
			$this->coordinator->cancel( $this->storeName );
		}
	}

	public function afterDeletionBatch( RebuildRun $run, int $removedInBatch, int $totalDeleted ): void {
	}

}
