<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Persistence\RebuildStartLock;
use ProfessionalWiki\NeoWiki\Persistence\RebuildStartLockUnavailableException;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Takes the start lock as a MediaWiki advisory database lock.
 *
 * `getScopedLockAndFlush` rather than a plain lock, because it also ends the snapshot the connection is
 * reading from: the check for an active run has to see the row a process that just released this lock
 * committed, which a snapshot taken before that commit would hide.
 */
class DatabaseRebuildStartLock implements RebuildStartLock {

	/**
	 * How long to wait for another process to finish starting a rebuild. Long enough to outlast that —
	 * a read and an insert — and short enough that a caller waiting on a lock nothing will release
	 * (a process killed mid-start) hears about it rather than hanging.
	 */
	private const int TIMEOUT_SECONDS = 10;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function whileHeld( string $storeName, Closure $start ): RebuildRun {
		// Named after the store, at full length: MediaWiki hashes a lock name its backend cannot hold.
		$unlocker = $this->connectionProvider->getPrimaryDatabase()->getScopedLockAndFlush(
			'NeoWiki-graph-rebuild-start:' . $storeName,
			__METHOD__,
			self::TIMEOUT_SECONDS
		);

		if ( $unlocker === null ) {
			throw new RebuildStartLockUnavailableException( $storeName );
		}

		return $start();
	}

}
