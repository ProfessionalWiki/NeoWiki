<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MappingNameLookup;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Caches deserialized Mappings, mirroring {@see CachingSchemaLookup}. Enumerating all Mappings (for
 * projection and for duplicate detection) reads every Mapping page; the cache key includes each page's
 * latest revision id, so a Mapping edit transparently invalidates its entry and a bulk RDF dump reuses
 * cached Mappings across pages instead of re-parsing them per page.
 */
class CachingMappingLookup implements MappingLookup {

	private const CACHE_VERSION = 1;

	public function __construct(
		private readonly MappingLookup $mappingLookup,
		private readonly MappingNameLookup $mappingNameLookup,
		private readonly WANObjectCache $cache,
		private readonly TitleFactory $titleFactory,
		private readonly Authority $authority,
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getMapping( MappingName $name ): ?Mapping {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_MAPPING );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// Repeat the inner lookup's per-user read check before the cache, so a cache hit cannot serve a
		// Mapping to a user who may not read its page.
		if ( !$this->authority->probablyCan( 'read', $title ) ) {
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

	/**
	 * @return Mapping[]
	 */
	public function getAllMappings(): array {
		$mappings = [];

		foreach ( $this->mappingNameLookup->getMappingNames() as $name ) {
			$mapping = $this->getMapping( $name );

			if ( $mapping !== null ) {
				$mappings[] = $mapping;
			}
		}

		return $mappings;
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
