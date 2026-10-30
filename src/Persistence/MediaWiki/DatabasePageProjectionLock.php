<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Closure;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLock;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLockUnavailableException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * Takes the lock as a MediaWiki advisory database lock, named after the wiki as well as the page, since
 * the wikis of a farm can share a database server.
 */
class DatabasePageProjectionLock implements PageProjectionLock {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly int $timeoutSeconds,
	) {
	}

	public function whileHeld( PageId $pageId, Closure $projection ): mixed {
		$database = $this->connectionProvider->getPrimaryDatabase();
		$lockName = 'NeoWiki-page-projection:' . $database->getDomainID() . ':' . $pageId->id;

		if ( !$database->lock( $lockName, __METHOD__, $this->timeoutSeconds ) ) {
			throw new PageProjectionLockUnavailableException( $pageId );
		}

		try {
			return $projection();
		}
		finally {
			$this->releaseOnceTheTransactionResolves( $database, $lockName );
		}
	}

	/**
	 * A database error leaves an open transaction unable to run the release until it is rolled back, and
	 * a rollback does not release an advisory lock.
	 */
	private function releaseOnceTheTransactionResolves( IDatabase $database, string $lockName ): void {
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
