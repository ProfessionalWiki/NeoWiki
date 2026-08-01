<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;

/**
 * The record of every graph rebuild, kept per store.
 *
 * It is what stops two rebuilds of one store running at once, and what a resumed run reads its cursor
 * from, so it outlives the process that wrote it.
 */
interface RebuildRunRepository {

	/**
	 * Records a rebuild of $store as started, and returns it with the id it was filed under.
	 */
	public function startRun( string $store, RebuildTrigger $trigger ): RebuildRun;

	/**
	 * Writes a run's status, progress and error back over the stored copy. A run that has reached a
	 * terminal status is stamped with the time it did.
	 */
	public function updateRun( RebuildRun $run ): void;

	/**
	 * The store's run that is still going, if any.
	 */
	public function getActiveRun( string $store ): ?RebuildRun;

	/**
	 * The store's most recently started run, whatever its status.
	 */
	public function getLatestRun( string $store ): ?RebuildRun;

}
