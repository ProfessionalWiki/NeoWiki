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
 * A page's Subject search hit, read from its current revision and kept for the request: both hooks
 * that build a row ask for it.
 */
class SubjectSearchHitLookup {

	/** @var array<string, ?SubjectSearchHit> Keyed by reader and page */
	private array $hits = [];

	public function __construct(
		private readonly RevisionLookup $revisionLookup,
		private readonly SubjectSearchHitBuilder $hitBuilder,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * @param string[] $terms The terms the engine reports, empty for an engine that reports none
	 * @param string $query The reader's query, the fallback for attribution
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
	 * For the Go button: where it leads instead of this page, or null.
	 */
	public function landingForTitle( Authority $authority, Title $title ): ?SubjectSearchLanding {
		return $this->buildHit( $authority, $title, null )?->landing;
	}

	/**
	 * A failure costs this row its Subject, not the results page.
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
		// Search results are not permission-filtered; a page the reader may not open shows nothing here.
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
	 * The recorded size rather than loaded content: this runs per result row.
	 */
	private function mainSlotSizeOf( RevisionRecord $revision ): int {
		return $revision->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getSize();
	}

}
