<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Psr\Log\LoggerInterface;
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
		private readonly LoggerInterface $logger,
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		$title = $this->titleFactory->newFromText( $schemaName->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The inner lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Schema read path. It
		// must also run before the cache: the cached value is user-independent schema content,
		// and a cache hit must not serve a Schema whose page the user may not read (#1046).
		if ( !$this->authority->authorizeRead( 'read', $title ) ) {
			$this->logger->info( 'NeoWiki: denied read of page {page} to {user}', [
				'page' => $title->getPrefixedDBkey(),
				'user' => $this->authority->getUser()->getName(),
			] );
			return null;
		}

		/** @var Schema|null $schema */
		$schema = $this->cache->getWithSetCallback(
			$this->makeCacheKey( $title ),
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

	/**
	 * Keyed by the Schema page's article id and latest revision id, so editing
	 * the Schema yields a new key and the old entry is never served again.
	 */
	private function makeCacheKey( Title $title ): string {
		return $this->cache->makeKey(
			'neowiki-schema',
			self::CACHE_VERSION,
			$title->getArticleID(),
			$title->getLatestRevID()
		);
	}

}
