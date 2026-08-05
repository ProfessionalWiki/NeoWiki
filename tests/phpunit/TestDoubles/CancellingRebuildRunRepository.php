<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;

/**
 * Cancels the run the first time it is read, after answering with it as it was.
 *
 * That is what a reader inside an open transaction sees when something cancels the run from another
 * connection: the copy it holds says the run is going, while the row says it is over. The test harness
 * runs every test in one transaction on one connection, so that divergence cannot be produced by
 * cancelling for real, and this stands in for it.
 */
class CancellingRebuildRunRepository implements RebuildRunRepository {

	private bool $cancelled = false;

	public function __construct(
		private readonly RebuildRunRepository $runs,
	) {
	}

	public function getRun( int $id ): ?RebuildRun {
		$run = $this->runs->getRun( $id );

		if ( $run !== null && !$this->cancelled ) {
			$this->cancelled = true;
			$this->runs->updateRun( $run->cancelled() );
		}

		return $run;
	}

	public function startRun( string $store, RebuildTrigger $trigger, RebuildStatus $status ): RebuildRun {
		return $this->runs->startRun( $store, $trigger, $status );
	}

	public function updateRun( RebuildRun $run ): void {
		$this->runs->updateRun( $run );
	}

	public function updateRunWhileActive( RebuildRun $run ): ?RebuildRun {
		return $this->runs->updateRunWhileActive( $run );
	}

	public function getActiveRun( string $store ): ?RebuildRun {
		return $this->runs->getActiveRun( $store );
	}

	public function cancelActiveRun( string $store ): ?RebuildRun {
		return $this->runs->cancelActiveRun( $store );
	}

	public function getLatestRun( string $store ): ?RebuildRun {
		return $this->runs->getLatestRun( $store );
	}

	public function getLastSuccessfulRun( string $store ): ?RebuildRun {
		return $this->runs->getLastSuccessfulRun( $store );
	}

}
