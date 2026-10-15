<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use InvalidArgumentException;
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
use ProfessionalWiki\NeoWiki\Persistence\PublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TypeError;

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

		$content = $this->readSubjectContent( $revisionRecord );

		if ( $content instanceof PageRefreshOutcome ) {
			return $content;
		}

		$pageId = new PageId( $revisionRecord->getPageId() );

		// Indexed before the Subjects are read as Subjects, and outside the projection's failure
		// isolation: the index is authoritative (ADR 32), so it commits with the revision that changes
		// it or not at all, and a Subject too broken to deserialize is still indexed.
		$this->subjectPageIndex->setSubjectsOfPage( $pageId, $content?->getSubjectIds() ?? [] );

		$published = $this->revisionPolicy->publishedRevision( $revisionRecord );

		if ( $published === null ) {
			$this->graphDatabasePlugin->deletePage( $pageId );
			$this->publishedSubjectIndex->removePage( $pageId );
			return PageRefreshOutcome::Unpublished;
		}

		$publishedContent = $published === $revisionRecord ? $content : $this->readSubjectContent( $published );

		if ( $publishedContent instanceof PageRefreshOutcome ) {
			return $publishedContent;
		}

		$subjects = $this->deserializeSubjects( $published, $publishedContent );

		if ( $subjects === null ) {
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
	 * Null when the slot holds Subject data that does not deserialize. The page is then left as it was:
	 * dropping what it published would hide its Subjects, and refusing the save would refuse the repair too.
	 */
	private function deserializeSubjects( RevisionRecord $published, ?SubjectContent $content ): ?PageSubjects {
		try {
			return $content?->getPageSubjects() ?? PageSubjects::newEmpty();
		} catch ( InvalidArgumentException | TypeError $exception ) {
			$this->logger->warning(
				'NeoWiki did not publish page {page}: the Subject data of its revision {revision} does not '
				. 'deserialize, so the graph stores and the published Subject index keep what they held for '
				. 'the page until it publishes Subject data that does. {message}',
				[
					'page' => $published->getPageId(),
					'revision' => $published->getId(),
					'message' => $exception->getMessage(),
					'exception' => $exception,
				]
			);

			return null;
		}
	}

	/**
	 * What the revision holds in its subject slot, or null when it has no such slot.
	 *
	 * A slot holding something that is not Subject content — its content model is not registered, or an
	 * import wrote something else into it — is neither, since reading such a page as holding no Subjects
	 * would drop the Subjects it does hold. SkippedUnreadableSubjects then says to write nothing at all.
	 */
	private function readSubjectContent( RevisionRecord $revision ): SubjectContent|PageRefreshOutcome|null {
		if ( !$revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return null;
		}

		// The slot exists; a read failure here is a genuine error and must propagate —
		// the refresh contract treats genuine failures as exceptions, not skips.
		$content = $revision->getSlots()->getContent( MediaWikiSubjectRepository::SLOT_NAME );

		if ( $content instanceof SubjectContent ) {
			return $content;
		}

		$this->logSkip(
			$revision,
			'its subject slot holds content that is not Subject data, so projecting the page would drop '
			. 'the Subjects it holds'
		);

		return PageRefreshOutcome::SkippedUnreadableSubjects;
	}

	private function logSkip( RevisionRecord $revisionRecord, string $reason ): void {
		$this->logger->warning(
			'NeoWiki did not project page ' . $revisionRecord->getPageId() . ' because ' . $reason
			. '. The graph and the published Subject index are out of sync for that page until the cause is '
			. 'resolved and the RebuildGraphDatabases and RebuildPublishedSubjectIndex maintenance scripts are run.'
		);
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
