<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\PagePropertiesSource;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;
use Psr\Log\LoggerInterface;
use RuntimeException;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly GraphDatabasePlugin $graphDatabasePlugin,
		private readonly SubjectPageIndex $subjectPageIndex,
		private readonly PagePropertiesSource $pagePropertiesSource,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Indexes which Subjects the page holds, and projects the page with them — and with none when it
	 * holds none: every page gets a Page node, so its Page Properties are queryable.
	 *
	 * The index records where a Subject lives and is written for every revision, published or not:
	 * every id-keyed read and write addresses its page through it, so a Subject missing from it cannot
	 * be edited, moved or deleted, and its id reads as free. Publishing is decided separately, and only
	 * for the graph, which is the surface readers query.
	 */
	public function onRevisionCreated( RevisionRecord $revisionRecord ): PageRefreshOutcome {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new RuntimeException( 'Page ID should not be 0' );
		}

		if ( !$revisionRecord->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return $this->refreshPage( $revisionRecord, null );
		}

		// The slot exists; a read failure here is a genuine error and must propagate —
		// the refresh contract treats genuine failures as exceptions, not skips.
		$content = $revisionRecord->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		// The slot holds something that is not Subject content, which happens when its content model is
		// not registered, or an import wrote something else into it. Reading such a page as holding no
		// Subjects would drop the Subjects it does hold, so nothing is written for it at all.
		if ( !$content instanceof SubjectContent ) {
			$this->logSkip(
				$revisionRecord,
				'its subject slot holds content that is not Subject data, so projecting the page would drop '
				. 'the Subjects it holds from the graph'
			);
			return PageRefreshOutcome::SkippedUnreadableSubjects;
		}

		return $this->refreshPage( $revisionRecord, $content );
	}

	private function refreshPage( RevisionRecord $revisionRecord, ?SubjectContent $content ): PageRefreshOutcome {
		$pageId = new PageId( $revisionRecord->getPageId() );

		// Indexed before the Subjects are read as Subjects, and outside the projection's failure
		// isolation: the index is authoritative (ADR 32), so it commits with the revision that changes
		// it or not at all, and a Subject too broken to deserialize is still indexed.
		$this->subjectPageIndex->setSubjectsOfPage( $pageId, $content?->getSubjectHeaders() ?? [] );

		// An unpublished revision leaves the graph holding what the policy last published.
		if ( !$this->revisionPolicy->publishesRevision( $revisionRecord ) ) {
			return PageRefreshOutcome::SkippedUnpublishableRevision;
		}

		$subjects = $content?->getPageSubjects() ?? PageSubjects::newEmpty();

		// Null only from the isolating source the hook path is given, which has already logged the
		// cause. The rebuild path is given the propagating one, so there the failure surfaces to the
		// maintenance script instead, which reports it against the page.
		$properties = $this->pagePropertiesSource->getPagePropertiesFor( $revisionRecord );

		if ( $properties === null ) {
			return PageRefreshOutcome::SkippedUnreadablePageProperties;
		}

		$this->graphDatabasePlugin->savePage(
			new Page(
				id: $pageId,
				properties: $properties,
				subjects: $subjects
			)
		);

		return PageRefreshOutcome::Refreshed;
	}

	private function logSkip( RevisionRecord $revisionRecord, string $reason ): void {
		$this->logger->warning(
			'NeoWiki did not project page ' . $revisionRecord->getPageId() . ' because ' . $reason
			. '. The graph is out of sync for that page until the cause is resolved and the '
			. 'RebuildGraphDatabases maintenance script is run.'
		);
	}

	public function onPageDelete( int $pageId ): void {
		$page = new PageId( $pageId );

		// The graph goes first because only the index removal can propagate: the projection write is
		// failure-isolated, so the other order lets a database fault in the index skip the graph
		// deletion and leave the deleted page's Subject values queryable. Rows the index keeps when
		// this order fails are inert, since reads join `page`.
		$this->graphDatabasePlugin->deletePage( $page );
		$this->subjectPageIndex->removePage( $page );
	}

}
