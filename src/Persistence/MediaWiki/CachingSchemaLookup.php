<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ObjectCache\WANObjectCache;

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
	) {
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		$title = $this->titleFactory->newFromText( $schemaName->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null || !$title->exists() ) {
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
			fn(): ?Schema => $this->schemaLookup->getSchema( $schemaName )
		);

		return $schema;
	}

}
