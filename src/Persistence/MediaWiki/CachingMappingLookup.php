<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Caches deserialized Mappings, mirroring {@see CachingSchemaLookup}. The cache key includes the Mapping
 * page's latest revision id, so a Mapping edit transparently invalidates its entry and a bulk RDF dump
 * reuses a cached Mapping across every page it projects instead of re-parsing it per page.
 */
class CachingMappingLookup implements MappingLookup {

	// The cached value is a Mapping; bumped from 1 when the stored/aggregate shape changed (one page per
	// target ontology), so a warm cache from before the change is not served in the old shape.
	private const CACHE_VERSION = 2;

	public function __construct(
		private readonly MappingLookup $mappingLookup,
		private readonly WANObjectCache $cache,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getMapping( MappingName $name ): ?Mapping {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_MAPPING );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The inner lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Mapping read path. It
		// must also run before the cache: the cached value is user-independent mapping content,
		// and a cache hit must not serve a Mapping whose page the user may not read (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		/** @var Mapping|null $mapping */
		$mapping = $this->cache->getWithSetCallback(
			$this->makeCacheKey( $title ),
			WANObjectCache::TTL_DAY,
			function ( mixed $oldValue, int &$ttl, array &$setOpts ) use ( $name ): ?Mapping {
				$setOpts += Database::getCacheSetOptions( $this->connectionProvider->getReplicaDatabase() );
				return $this->mappingLookup->getMapping( $name );
			}
		);

		return $mapping;
	}

	private function makeCacheKey( Title $title ): string {
		return $this->cache->makeKey(
			'neowiki-mapping',
			self::CACHE_VERSION,
			$title->getArticleID(),
			$title->getLatestRevID()
		);
	}

}
