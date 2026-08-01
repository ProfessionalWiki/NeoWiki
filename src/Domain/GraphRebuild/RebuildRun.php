<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * One rebuild of one graph store: its progress while it runs, and its record afterwards.
 *
 * The cursor is the last page id projected, so a run picked up again continues after it rather than
 * re-projecting what is already reconciled. Its counters cover both phases of a rebuild: `processed`
 * counts the pages projected, and `failed` every page the run could not reconcile, whether projecting
 * or removing it.
 *
 * Only `failed()` leaves an error behind; every other transition clears it, so a run never carries the
 * error of something it has since moved past.
 *
 * Timestamps are assigned where the run is stored, so this stays free of both a clock and a database.
 */
readonly class RebuildRun {

	public function __construct(
		public int $id,
		public string $store,
		public RebuildStatus $status,
		public int $cursor,
		public int $processed,
		public int $failed,
		public RebuildTrigger $trigger,
		public ?string $error = null,
	) {
	}

	public function withProgress( int $cursor, int $processed, int $failed ): self {
		return $this->with( status: $this->status, cursor: $cursor, processed: $processed, failed: $failed );
	}

	public function succeeded(): self {
		return $this->with( status: RebuildStatus::Succeeded );
	}

	/**
	 * The run hit something that stops it reconciling any further page — the store being unreachable,
	 * say — so it ends here, with the cursor a later `--resume` picks up from. The error is null when
	 * whatever ended the run said nothing about why.
	 */
	public function failed( ?string $error ): self {
		return $this->with( status: RebuildStatus::Failed, error: $error );
	}

	/**
	 * Picks a terminal run back up: it runs again from where it left off, and the error that ended it
	 * no longer describes it.
	 */
	public function reopened(): self {
		return $this->with( status: RebuildStatus::Running );
	}

	private function with(
		RebuildStatus $status,
		?int $cursor = null,
		?int $processed = null,
		?int $failed = null,
		?string $error = null,
	): self {
		return new self(
			id: $this->id,
			store: $this->store,
			status: $status,
			cursor: $cursor ?? $this->cursor,
			processed: $processed ?? $this->processed,
			failed: $failed ?? $this->failed,
			trigger: $this->trigger,
			error: $error,
		);
	}

}
