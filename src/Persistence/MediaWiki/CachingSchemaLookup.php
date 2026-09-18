<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\Schema\Exception\SchemaContentUnavailableException;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Caches Schemas in two tiers: the shared WANObjectCache holds the Schema page's JSON, and a
 * process-local one holds the Schema parsed from it. The shared tier holds text rather than the
 * Schema because a persistent cache outlives a deploy, and a PHP-serialized object written by one
 * build fails to unserialize under the next once its class changed shape; the stored JSON is what
 * the deserializer reads by contract. NeoWikiExtension pins one lookup in its singleton, so the
 * process-local tier lasts as long as the PHP process — a single request under mod_php, an entire
 * run under a maintenance script.
 *
 * Both key on the Schema page's latest revision id, which is read from the LinkCache. An edit made
 * by this process refreshes that cache (see WikiPage::updateRevisionOn), so it yields a new key and
 * takes effect at once. An edit made by another process does not reach this one's LinkCache, so a
 * long-running script keeps serving the Schema as it stood when the script first resolved it. The
 * shared tier has no such window, because the next process reads the current revision id.
 */
class CachingSchemaLookup implements SchemaLookup {

	// Bump when what an entry holds changes. Changes to the Schema classes do not: an entry holds
	// the page's JSON.
	private const CACHE_VERSION = 2;

	/**
	 * @var array<string, ?Schema>
	 */
	private array $resolvedSchemas = [];

	public function __construct(
		private readonly SchemaJsonLookup $schemaJsonLookup,
		private readonly SchemaPersistenceDeserializer $schemaDeserializer,
		private readonly WANObjectCache $cache,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly ReplicaCacheOptions $cacheOptions,
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		$title = $this->titleFactory->newFromText( $schemaName->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The JSON lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Schema read path. It
		// must also run before the caches: the cached value is user-independent schema content,
		// and a cache hit must not serve a Schema whose page the user may not read (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		$cacheKey = $this->makeCacheKey( $title );

		if ( !array_key_exists( $cacheKey, $this->resolvedSchemas ) ) {
			try {
				$json = $this->getJsonFromSharedCache( $cacheKey, $schemaName );
			}
			catch ( SchemaContentUnavailableException ) {
				// The revision's content could not be read, which the next call may well manage.
				// Neither tier may remember that: the key is the revision id, so the entry would
				// outlive the failure until someone edited the Schema. Letting this unwind past
				// getWithSetCallback() leaves the shared tier empty too.
				return null;
			}

			$this->resolvedSchemas[$cacheKey] = $this->deserialize( $schemaName, $json );
		}

		return $this->resolvedSchemas[$cacheKey];
	}

	private function getJsonFromSharedCache( string $cacheKey, SchemaName $schemaName ): string {
		/** @var string $json */
		$json = $this->cache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function ( mixed $oldValue, int &$ttl, array &$setOpts ) use ( $schemaName ): string {
				$setOpts += $this->cacheOptions->forRead();
				return $this->schemaJsonLookup->getSchemaJson( $schemaName );
			}
		);

		return $json;
	}

	/**
	 * JSON that is not a valid Schema will not become one on the next call, so the process-local
	 * tier remembers the null. The shared tier keeps the text either way: parsing it again in the
	 * next process is cheaper than reading the page again.
	 */
	private function deserialize( SchemaName $schemaName, string $json ): ?Schema {
		try {
			return $this->schemaDeserializer->deserialize( $schemaName, $json );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Keyed by the Schema page's article id and latest revision id, so an edit this process can see
	 * yields a new key and the old entry is never served again. The class docblock covers the edits
	 * it cannot see.
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
