<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Search;

use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\Search\SearchTermMatcher;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHit;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchHitBuilder;
use ProfessionalWiki\NeoWiki\Application\Search\SubjectSearchLanding;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectSlotReader;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What the Subjects of a searched page say about its result row, read off the page's current
 * revision. Two hooks build one row between them, so what a page answered is kept for the second
 * of them.
 */
class SubjectSearchHitLookup {

	/** @var array<string, ?SubjectSearchHit> Keys are the reader and the page: what a row may show depends on both */
	private array $hits = [];

	public function __construct(
		private readonly RevisionLookup $revisionLookup,
		private readonly SubjectSearchHitBuilder $hitBuilder,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * Answered once per page: a page listed twice in one set of results was found by the same query
	 * both times.
	 *
	 * @param string[] $terms What the engine says it matched, empty for the engines that do not say
	 * @param string $query What the reader typed, to fall back on when the engine does not say
	 */
	public function forRow( Authority $authority, Title $title, array $terms, string $query ): ?SubjectSearchHit {
		$key = $authority->getUser()->getName() . '|' . $title->getArticleID();

		if ( !array_key_exists( $key, $this->hits ) ) {
			$this->hits[$key] = $this->buildHit(
				$authority,
				$title,
				SearchTermMatcher::fromEngineTerms( $terms ) ?? SearchTermMatcher::fromQuery( $query )
			);
		}

		return $this->hits[$key];
	}

	/**
	 * Where the Go button should lead instead of this page, or null to leave it alone. Asked while
	 * the reader is still being routed, so it is not part of what a row answered.
	 */
	public function landingForTitle( Authority $authority, Title $title ): ?SubjectSearchLanding {
		return $this->buildHit( $authority, $title, null )?->landing;
	}

	/**
	 * A page of search results is worth more than the Subjects it could have shown, so anything that
	 * goes wrong below costs this row its Subject and leaves the rest of the page standing.
	 */
	private function buildHit( Authority $authority, Title $title, ?SearchTermMatcher $matcher ): ?SubjectSearchHit {
		try {
			return $this->readHit( $authority, $title, $matcher );
		}
		catch ( Throwable $e ) {
			$this->logger->warning(
				'NeoWiki could not read the Subjects of the search result for {page}: {reason}',
				[ 'page' => $title->getPrefixedText(), 'reason' => $e->getMessage(), 'exception' => $e ]
			);

			return null;
		}
	}

	private function readHit( Authority $authority, Title $title, ?SearchTermMatcher $matcher ): ?SubjectSearchHit {
		// Search results are not filtered by what a reader may read, and a page they may not open
		// must not start showing them its contents here.
		if ( !$title->canExist() || !$authority->definitelyCan( 'read', $title ) ) {
			return null;
		}

		$revision = $this->revisionLookup->getKnownCurrentRevision( $title );

		if ( $revision === false ) {
			return null;
		}

		return $this->hitBuilder->build(
			$this->pageSubjectsOf( $revision ),
			$title->getPrefixedText(),
			$this->mainSlotSizeOf( $revision ) > 0,
			$matcher
		);
	}

	private function pageSubjectsOf( RevisionRecord $revision ): PageSubjects {
		return SubjectSlotReader::read( $revision )?->getPageSubjects() ?? PageSubjects::newEmpty();
	}

	/**
	 * The size the slot records, rather than the size of content loaded to measure it: a row is
	 * rendered for every hit on the page of results.
	 */
	private function mainSlotSizeOf( RevisionRecord $revision ): int {
		return $revision->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getSize();
	}

}
