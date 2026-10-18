<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFormatter;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\PagePropertiesSource;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\UnreadableSubjectsException;
use ProfessionalWiki\NeoWiki\Persistence\PublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;
use Psr\Log\LoggerInterface;
use RuntimeException;

class OnRevisionCreatedHandler {

	public function __construct(
		private readonly GraphDatabasePlugin $graphDatabasePlugin,
		private readonly SubjectPageIndex $subjectPageIndex,
		private readonly PublishedSubjectIndex $publishedSubjectIndex,
		private readonly PagePropertiesSource $pagePropertiesSource,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly TitleFormatter $titleFormatter,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Indexes which Subjects the page holds, and publishes the revision the registered policy names for
	 * it, to the published Subject index and to the graph — with no Subjects when that revision holds
	 * none: every page gets a Page node, so its Page Properties are queryable. A page the policy
	 * publishes nothing for is withdrawn from both.
	 *
	 * The subject -> page index records where a Subject lives and is written for every revision,
	 * published or not: every id-keyed read and write addresses its page through it, so a Subject
	 * missing from it cannot be edited, moved or deleted, and its id reads as free. Publishing is decided
	 * separately, for the surfaces readers query.
	 */
	public function onRevisionCreated( RevisionRecord $revisionRecord ): PageRefreshOutcome {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new RuntimeException( 'Page ID should not be 0' );
		}

		$pageId = new PageId( $revisionRecord->getPageId() );

		// Before the Subjects are read as Subjects, and outside the projection's failure isolation: the index is
		// authoritative, so it commits with the revision that changes it or not at all, and a Subject too broken to
		// deserialize is still indexed.
		$this->indexSubjectsOfPage( $pageId, $revisionRecord );

		$published = $this->revisionPolicy->publishedRevision( $revisionRecord );

		if ( $published === null ) {
			$this->graphDatabasePlugin->deletePage( $pageId );
			$this->publishedSubjectIndex->removePage( $pageId );
			return PageRefreshOutcome::Unpublished;
		}

		try {
			$subjects = SubjectsToPublish::of( $published );
		} catch ( UnreadableSubjectsException $exception ) {
			// Logged rather than thrown: refusing the save would refuse the edit that repairs the page too.
			$this->logger->warning(
				'NeoWiki did not publish page {page}: its revision {revision} cannot be read as Subject data, so '
				. 'the graph stores and the published Subject index keep what they held for the page until it '
				. 'publishes Subject data that can be. {message}',
				[
					'page' => $published->getPageId(),
					'revision' => $published->getId(),
					'message' => $exception->getMessage(),
					'exception' => $exception,
				]
			);

			return PageRefreshOutcome::SkippedUnreadableSubjects;
		}

		// Outside the projection's failure isolation, so it commits with the revision or not at all. The
		// title comes from the revision that fired this: a move retitles the page without changing which
		// revision the policy publishes.
		$this->publishedSubjectIndex->setSubjectsOfPage(
			$pageId,
			$this->titleFormatter->getPrefixedText( $revisionRecord->getPage() ),
			$subjects,
			(string)$published->getTimestamp()
		);

		return $this->projectPage( $pageId, $published, $subjects );
	}

	/**
	 * Content that is not Subject data leaves the page's entries as they are: indexing it as holding none would drop
	 * the Subjects it holds.
	 */
	private function indexSubjectsOfPage( PageId $pageId, RevisionRecord $revision ): void {
		// A slot that exists but cannot be loaded is a failure, not a skip, so it propagates.
		$content = $revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME )
			? $revision->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME )
			: null;

		if ( $content === null || $content instanceof SubjectContent ) {
			$this->subjectPageIndex->setSubjectsOfPage( $pageId, $content?->getSubjectIds() ?? [] );
		}
	}

	private function projectPage(
		PageId $pageId,
		RevisionRecord $published,
		PageSubjects $subjects
	): PageRefreshOutcome {
		// Null only from the isolating source the hook path is given, which has already logged the
		// cause. The rebuild path is given the propagating one, so there the failure surfaces to the
		// maintenance script instead, which reports it against the page.
		$properties = $this->pagePropertiesSource->getPagePropertiesFor( $published );

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

	public function onPageDelete( int $pageId ): void {
		$page = new PageId( $pageId );

		// The graph goes first because only the index removals can propagate: the projection write is
		// failure-isolated, so the other order lets a database fault in an index skip the graph
		// deletion and leave the deleted page's Subject values queryable. Rows the subject -> page index
		// keeps when this order fails are inert, since reads join `page`; the published Subject index's
		// stay in the Subject counts until RebuildPublishedSubjectIndex runs.
		$this->graphDatabasePlugin->deletePage( $page );
		$this->subjectPageIndex->removePage( $page );
		$this->publishedSubjectIndex->removePage( $page );
	}

}
