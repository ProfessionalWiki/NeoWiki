<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFormatter;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\UnreadableSubjectsException;
use ProfessionalWiki\NeoWiki\Persistence\PublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;
use RuntimeException;

/**
 * Writes the indexes NeoWiki's own features read rather than a graph. They commit with the operation that
 * changes the page or not at all, so a failure fails that operation.
 */
class SubjectIndexer {

	public function __construct(
		private readonly SubjectPageIndex $subjectPageIndex,
		private readonly PublishedSubjectIndex $publishedSubjectIndex,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly TitleFormatter $titleFormatter,
	) {
	}

	/**
	 * Indexes which Subjects the page holds, and the Subjects of the revision the registered policy publishes
	 * for it in the published Subject index, which drops a page the policy publishes nothing for. Published
	 * Subjects that cannot be read leave the published Subject index as it was.
	 *
	 * The subject -> page index records where a Subject lives and is written for every revision,
	 * published or not: every id-keyed read and write addresses its page through it, so a Subject
	 * missing from it cannot be edited, moved or deleted, and its id reads as free. Publishing is decided
	 * separately, for the surfaces readers query.
	 */
	public function indexRevision( RevisionRecord $revisionRecord ): void {
		if ( $revisionRecord->getPageId() === 0 ) {
			throw new RuntimeException( 'Page ID should not be 0' );
		}

		$pageId = new PageId( $revisionRecord->getPageId() );

		// Before the Subjects are read as Subjects, so a Subject too broken to deserialize is still indexed.
		$this->indexSubjectsOfPage( $pageId, $revisionRecord );
		$this->indexPublishedSubjectsOfPage( $pageId, $revisionRecord );
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

	private function indexPublishedSubjectsOfPage( PageId $pageId, RevisionRecord $revision ): void {
		$published = $this->revisionPolicy->publishedRevision( $revision );

		if ( $published === null ) {
			$this->publishedSubjectIndex->removePage( $pageId );
			return;
		}

		try {
			$subjects = SubjectsToPublish::of( $published );
		} catch ( UnreadableSubjectsException ) {
			// The graph projection of the page logs it.
			return;
		}

		// The title comes from the revision that fired this: a move retitles the page without changing which
		// revision the policy publishes.
		$this->publishedSubjectIndex->setSubjectsOfPage(
			$pageId,
			$this->titleFormatter->getPrefixedText( $revision->getPage() ),
			$subjects,
			(string)$published->getTimestamp()
		);
	}

	public function unindexPage( PageId $pageId ): void {
		$this->subjectPageIndex->removePage( $pageId );
		$this->publishedSubjectIndex->removePage( $pageId );
	}

}
