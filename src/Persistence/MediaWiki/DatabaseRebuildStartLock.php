<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Closure;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildStartLock;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\RebuildStartLockUnavailableException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * Takes the start lock as a MediaWiki advisory database lock.
 *
 * The snapshot the connection reads from is ended once the lock is held, because the check for an
 * active run has to see the row a process that just released this lock committed, which a snapshot
 * taken before that commit would hide. The lock is then held until that process's own insert commits,
 * for the same reason in reverse.
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
		$database = $this->connectionProvider->getPrimaryDatabase();
		// Named after the store, at full length: MediaWiki hashes a lock name its backend cannot hold.
		$lockName = 'NeoWiki-graph-rebuild-start:' . $storeName;

		if ( !$database->lock( $lockName, __METHOD__, self::TIMEOUT_SECONDS ) ) {
			throw new RebuildStartLockUnavailableException( $storeName );
		}

		$database->flushSnapshot( __METHOD__ );

		try {
			return $start();
		}
		finally {
			$this->releaseOnceTheRunIsCommitted( $database, $lockName );
		}
	}

	/**
	 * Releasing the lock while the started run is still uncommitted would let the next process take it
	 * and find no active run, which is what holding the lock is for.
	 */
	private function releaseOnceTheRunIsCommitted( IDatabase $database, string $lockName ): void {
		$method = __METHOD__;

		if ( $database->trxLevel() ) {
			$database->onTransactionResolution(
				static function () use ( $database, $lockName, $method ): void {
					$database->unlock( $lockName, $method );
				},
				$method
			);

			return;
		}

		$database->unlock( $lockName, $method );
	}

}
