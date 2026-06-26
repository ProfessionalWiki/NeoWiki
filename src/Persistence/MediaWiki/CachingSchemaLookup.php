<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Caches deserialized Schemas so that concurrent reads (most notably the
 * per-keystroke dry-run validation) do not each re-load the Schema wiki page
 * and re-parse it. The cache key includes the Schema page's latest revision id,
 * so editing the Schema transparently invalidates the entry — no stale schemas.
 */
class CachingSchemaLookup implements SchemaLookup {

	private const CACHE_VERSION = 1;

	public function __construct(
		private readonly SchemaLookup $schemaLookup,
		private readonly WANObjectCache $cache,
		private readonly TitleFactory $titleFactory,
		private readonly Authority $authority,
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		$title = $this->titleFactory->newFromText( $schemaName->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The cached value is user-independent schema content; the inner lookup
		// also applies a per-user read check while loading. Repeat it here,
		// before the cache, so a cache hit cannot serve a Schema to a user who
		// may not read its page.
		if ( !$this->authority->probablyCan( 'read', $title ) ) {
			return null;
		}

		/** @var Schema|null $schema */
		$schema = $this->cache->getWithSetCallback(
			$this->cache->makeKey(
				'neowiki-schema',
				self::CACHE_VERSION,
				$title->getArticleID(),
				$title->getLatestRevID()
			),
			WANObjectCache::TTL_DAY,
			function ( mixed $oldValue, int &$ttl, array &$setOpts ) use ( $schemaName ): ?Schema {
				// Make caching replica-lag aware: if the schema content is read
				// from a lagged replica, WANObjectCache reduces the TTL instead of
				// pinning that content under the new revision's key for the full
				// TTL. Closes the narrow read-after-edit staleness window.
				$setOpts += Database::getCacheSetOptions( $this->connectionProvider->getReplicaDatabase() );
				return $this->schemaLookup->getSchema( $schemaName );
			}
		);

		return $schema;
	}

}
