<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\MappingLookup;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Domain\Mapping\Mapping;
use ProfessionalWiki\NeoWiki\Domain\Mapping\MappingName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Caches the JSON of Mapping pages in the shared WANObjectCache, so that the SPARQL store, which
 * resolves its projection on every page save, does not re-load the Mapping page each time. It holds
 * the JSON rather than the parsed Mapping for the reason given in {@see CachingSchemaLookup}.
 *
 * Entries key on the Mapping page's latest revision id, so an edit transparently invalidates the
 * entry.
 */
class CachingMappingLookup implements MappingLookup {

	// Bump when what an entry holds changes. Changes to the Mapping classes do not: an entry holds
	// the page's JSON.
	private const CACHE_VERSION = 3;

	public function __construct(
		private readonly MappingJsonLookup $mappingJsonLookup,
		private readonly MappingPersistenceDeserializer $mappingDeserializer,
		private readonly WANObjectCache $cache,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly ReplicaCacheOptions $cacheOptions,
	) {
	}

	public function getMapping( MappingName $name ): ?Mapping {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_MAPPING );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The JSON lookup applies no per-title read check (its revision audience check filters
		// revision deletion only), so this is the sole read gate on the Mapping read path. It
		// must also run before the cache: the cached value is user-independent mapping content,
		// and a cache hit must not serve a Mapping whose page the user may not read (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		$json = $this->getJsonFromSharedCache( $this->makeCacheKey( $title ), $name );

		if ( $json === null ) {
			return null;
		}

		return $this->deserialize( $title, $json );
	}

	private function getJsonFromSharedCache( string $cacheKey, MappingName $name ): ?string {
		/** @var string|null $json */
		$json = $this->cache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
			function ( mixed $oldValue, int &$ttl, array &$setOpts ) use ( $name ): ?string {
				$setOpts += $this->cacheOptions->forRead();
				return $this->mappingJsonLookup->getMappingJson( $name );
			}
		);

		return $json;
	}

	/**
	 * Names the Mapping after its page rather than the request: MediaWiki capitalizes a title's
	 * first letter, so a projection requested as "eDM" and one requested as "EDM" (the same page)
	 * mint the same projector, serializer, and named-graph IRI. A page whose title is no usable
	 * Mapping name, such as one imported under the reserved "Native", yields null like content that
	 * does not deserialize.
	 */
	private function deserialize( Title $title, string $json ): ?Mapping {
		try {
			return $this->mappingDeserializer->deserialize( new MappingName( $title->getText() ), $json );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
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
