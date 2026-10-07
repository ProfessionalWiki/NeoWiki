<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Options that keep a cached value's TTL replica-lag aware, so content read from a lagged replica is
 * not pinned under a new revision's key for the full TTL.
 *
 * MediaWiki 1.47 took the job over: its WANObjectCache derives this itself, ignores these options,
 * and deprecates the call producing them.
 */
class ReplicaCacheOptions {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly string $mediaWikiVersion,
	) {
	}

	/**
	 * The version is injected rather than read from MW_VERSION here because PHPStan resolves that
	 * constant to a literal and would call one of these branches dead.
	 *
	 * @return array<string, mixed>
	 */
	public function forRead(): array {
		if ( version_compare( $this->mediaWikiVersion, '1.47', '<' ) ) {
			return Database::getCacheSetOptions( $this->connectionProvider->getReplicaDatabase() );
		}

		return [];
	}

}
