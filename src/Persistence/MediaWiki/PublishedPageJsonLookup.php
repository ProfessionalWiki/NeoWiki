<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use LogicException;
use MediaWiki\Content\JsonContent;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * The JSON of the revision a page publishes, cached in the shared WANObjectCache under that revision's
 * id. Which revision a page publishes is asked on every call, so an edit or an approval change takes
 * effect at once.
 *
 * Entries hold text rather than parsed Schemas or Mappings because a persistent cache outlives a
 * deploy, and a PHP-serialized object written by one build fails to unserialize under the next once
 * its class changed shape.
 */
class PublishedPageJsonLookup {

	// Bump when what an entry holds changes.
	private const int CACHE_VERSION = 1;

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly WANObjectCache $cache,
	) {
	}

	/**
	 * Null when the page publishes no revision, or when the revision's content could not be read,
	 * which the next call may well manage and so is not cached.
	 */
	public function getJson( Title $page ): ?string {
		$revision = $this->pageContentFetcher->getPublishedRevision( $page );

		if ( $revision === null ) {
			return null;
		}

		/** @var string|false $json */
		$json = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'neowiki-published-json', self::CACHE_VERSION, (int)$revision->getId() ),
			WANObjectCache::TTL_DAY,
			fn (): string|false => $this->readJson( $revision )
		);

		return $json === false ? null : $json;
	}

	/**
	 * False, which the cache does not store, when the content could not be read.
	 */
	private function readJson( RevisionRecord $revision ): string|false {
		$content = $this->pageContentFetcher->getRevisionContent( $revision, $this->authority, SlotRecord::MAIN );

		if ( $content instanceof JsonContent ) {
			return $content->getText();
		}

		if ( $content === null ) {
			return false;
		}

		throw new LogicException( 'Unexpected content type: not a JsonContent' );
	}

}
