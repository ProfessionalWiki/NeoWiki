<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;

/**
 * The record of every graph rebuild, kept per store.
 *
 * It is what stops two rebuilds of one store running at once, what a resumed run reads its cursor from,
 * and what a running one is cancelled through, so it outlives the process that wrote it.
 */
interface RebuildRunRepository {

	/**
	 * Records a rebuild of $store as begun, and returns it with the id it was filed under. A run started
	 * as Queued is one nothing is working on yet; one started as Running is being worked on by its
	 * caller.
	 */
	public function startRun( string $store, RebuildTrigger $trigger, RebuildStatus $status ): RebuildRun;

	/**
	 * Writes a run's status, phase, progress and error back over the stored copy. A run that has reached
	 * a terminal status is stamped with the time it did.
	 *
	 * Unconditional, so it is for the transitions made from outside a run — cancelling one, or picking a
	 * terminal one back up. A rebuild recording its own progress uses {@see self::updateRunWhileActive()}.
	 */
	public function updateRun( RebuildRun $run ): void;

	/**
	 * Writes the run back only if the records still have it queued or running.
	 *
	 * A batch reads the run, spends minutes projecting, and writes back what it got through, carrying the
	 * status it read. Something ending the run in that window — a cancellation, most of all — would be
	 * written straight back over. This is how that write is refused instead.
	 *
	 * @return ?RebuildRun The run as the records now hold it: the one just written when the write landed,
	 *         and whatever ended it when it did not. Null only when there is no such run to write to.
	 */
	public function updateRunWhileActive( RebuildRun $run ): ?RebuildRun;

	/**
	 * The run under this id, whatever its status. How a process that did not start a run reads what has
	 * become of it — a background batch picking one up, or a running one checking whether it has been
	 * cancelled.
	 */
	public function getRun( int $id ): ?RebuildRun;

	/**
	 * The store's run that is queued or going, if any.
	 */
	public function getActiveRun( string $store ): ?RebuildRun;

	/**
	 * Ends the store's rebuild, and returns it as it now stands. Null when the store had none to end.
	 *
	 * Which run that is and the write that ends it are one step, so a run that reaches the end of the wiki
	 * while it is being cancelled stays recorded as having reconciled it. Read first and written back
	 * after, cancelling would overwrite that, and the store would be reported as never rebuilt.
	 *
	 * Only the status is written: how far the run got is whatever the records say by then, not what the
	 * caller last saw.
	 */
	public function cancelActiveRun( string $store ): ?RebuildRun;

	/**
	 * The store's most recently started run, whatever its status.
	 */
	public function getLatestRun( string $store ): ?RebuildRun;

	/**
	 * The store's most recently started run that reconciled the whole wiki. Null when it has never been
	 * rebuilt through to the end, which is what "never built" means.
	 */
	public function getLastSuccessfulRun( string $store ): ?RebuildRun;

}
