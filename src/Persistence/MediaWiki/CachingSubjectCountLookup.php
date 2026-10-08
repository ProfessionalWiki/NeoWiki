<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\SubjectCountLookup;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Holds the counts for up to a minute, because counting reads all of the wiki's Subjects: about a quarter of a second
 * per million of them. A count can thus lag a created or deleted Subject by up to a minute.
 */
class CachingSubjectCountLookup implements SubjectCountLookup {

	// Bump when what an entry holds changes.
	private const int CACHE_VERSION = 1;

	public function __construct(
		private readonly SubjectCountLookup $lookup,
		private readonly WANObjectCache $cache,
	) {
	}

	public function getSubjectCountsBySchema(): array {
		/** @var array<string, int> $counts */
		$counts = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'neowiki-subject-counts', self::CACHE_VERSION ),
			WANObjectCache::TTL_MINUTE,
			fn (): array => $this->lookup->getSubjectCountsBySchema()
		);

		return $counts;
	}

}
