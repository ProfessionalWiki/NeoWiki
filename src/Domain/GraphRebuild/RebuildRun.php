<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\GraphRebuild;

/**
 * One rebuild of one graph store: its progress while it runs, and its record afterwards.
 *
 * The cursor is the last page id the run dealt with in its current {@see RebuildPhase}, so a run picked
 * up again continues after it rather than redoing what is already reconciled. Entering the removal phase
 * resets it, since the two phases walk different sets of page ids. Its counters cover both phases:
 * `processed` counts the pages projected, and `failed` every page the run could not reconcile, whether
 * projecting or removing it.
 *
 * Only `failed()` leaves an error behind; every other transition clears it, so a run never carries the
 * error of something it has since moved past.
 *
 * `started` and `finished` are what the record says, so they are null on a run built in memory and set
 * on one read back. A transition made here does not stamp them: the repository does, when it stores the
 * run.
 */
readonly class RebuildRun {

	public function __construct(
		public int $id,
		public string $store,
		public RebuildStatus $status,
		public RebuildPhase $phase,
		public int $cursor,
		public int $processed,
		public int $failed,
		public RebuildTrigger $trigger,
		public ?string $error = null,
		public ?string $started = null,
		public ?string $finished = null,
	) {
	}

	public function withProgress( int $cursor, int $processed, int $failed ): self {
		return $this->with( status: $this->status, cursor: $cursor, processed: $processed, failed: $failed );
	}

	/**
	 * The wiki's pages are all reprojected, so the run moves on to removing the pages MediaWiki no longer
	 * has. The cursor restarts because that phase walks its own page ids.
	 */
	public function enteredDeletionPhase(): self {
		return $this->with( status: $this->status, phase: RebuildPhase::Deletions, cursor: 0 );
	}

	/**
	 * The run is now working: it was queued and has reached the front, or it is a terminal run being
	 * picked back up. Either way it continues from where it left off, in the phase it left off in, and
	 * the error that ended it no longer describes it.
	 */
	public function started(): self {
		return $this->with( status: RebuildStatus::Running );
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

	public function cancelled(): self {
		return $this->with( status: RebuildStatus::Cancelled );
	}

	private function with(
		RebuildStatus $status,
		?RebuildPhase $phase = null,
		?int $cursor = null,
		?int $processed = null,
		?int $failed = null,
		?string $error = null,
	): self {
		return new self(
			id: $this->id,
			store: $this->store,
			status: $status,
			phase: $phase ?? $this->phase,
			cursor: $cursor ?? $this->cursor,
			processed: $processed ?? $this->processed,
			failed: $failed ?? $this->failed,
			trigger: $this->trigger,
			error: $error,
			started: $this->started,
			finished: $this->finished,
		);
	}

}
