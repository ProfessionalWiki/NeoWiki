<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;

/**
 * Serializes the starting of a store's rebuilds across processes.
 *
 * Whether a store may have another rebuild is decided by reading its run records and then writing one,
 * which two processes can interleave: both read no active run, both file one, and the store ends up with
 * two runs projecting over each other under two cursors. Holding this across the read and the write is
 * what makes that pair one step. It is per store, so rebuilding one never waits on another.
 */
interface RebuildStartLock {

	/**
	 * Starts a run with the store's start lock held, and returns the run that was started.
	 *
	 * @param Closure(): RebuildRun $start
	 *
	 * @throws RebuildStartLockUnavailableException When the lock could not be taken.
	 */
	public function whileHeld( string $storeName, Closure $start ): RebuildRun;

}
