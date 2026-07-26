<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Caches deserialized Schemas so that repeated reads (the per-keystroke dry-run validation, and
 * the validation plus graph projection of every Subject on a saved page) do not each re-load the
 * Schema wiki page and re-parse it. Caching is two-tier: a process-local tier serving the calls
 * within one request or maintenance run, over the shared WANObjectCache serving calls across them.
 *
 * Both tiers key on the Schema page's latest revision id, so editing the Schema transparently
 * invalidates the entry — no stale schemas. Neither tier is a read gate: the per-page read check
 * runs on every call, ahead of both.
 */
class CachingSchemaLookup implements SchemaLookup {

	private const CACHE_VERSION = 1;

	/**
	 * Resolved Schemas by cache key, including the nulls, so a Schema that is missing or
	 * unreadable is not re-resolved per Subject either.
	 *
	 * @var array<string, ?Schema>
	 */
	private array $resolvedSchemas = [];

	public function __construct(
		private readonly SchemaLookup $schemaLookup,
		private readonly WANObjectCache $cache,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		$title = $this->titleFactory->newFromText( $schemaName->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The inner lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Schema read path. It
		// must also run before the caches: the cached value is user-independent schema content,
		// and a cache hit must not serve a Schema whose page the user may not read (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		$cacheKey = $this->makeCacheKey( $title );

		if ( !array_key_exists( $cacheKey, $this->resolvedSchemas ) ) {
			$this->resolvedSchemas[$cacheKey] = $this->getFromSharedCache( $cacheKey, $schemaName );
		}

		return $this->resolvedSchemas[$cacheKey];
	}

	private function getFromSharedCache( string $cacheKey, SchemaName $schemaName ): ?Schema {
		/** @var Schema|null $schema */
		$schema = $this->cache->getWithSetCallback(
			$cacheKey,
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
	 * the Schema yields a new key and the old entry is never served again by
	 * either tier.
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
