<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use Closure;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use stdClass;
use Throwable;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * The Subject whose label stands in for the title of each page titled by a Subject id (ADR 31), as
 * the wiki publishes the page, for lists that link to many pages at once, such as Recent changes. A
 * list reads its pages up front, in one batch, and they are kept for the rendering of that list, so
 * its links read nothing of their own.
 *
 * What a list reads is bounded, since anyone can ask for a long list: a page past the first
 * $maxPages, or whose Subjects take more than MAX_SLOT_SIZE bytes, keeps its title.
 */
class SubjectInPlaceOfPageTitleLookup {

	private const int MAX_SLOT_SIZE = 65536;

	/** @var array<string, Subject> Keyed by prefixed database key */
	private array $subjects = [];

	/** @var array<int, true> Keyed by page id */
	private array $readPageIds = [];

	/** @var array<string, bool> Keyed by reader and page, so a list's many links to one page ask once */
	private array $readable = [];

	private ?OutputPage $listOutput = null;

	/**
	 * @param Closure(Authority): PageReadAuthorizer $newReadAuthorizer
	 */
	public function __construct(
		private readonly RevisionStore $revisionStore,
		private readonly IReadableDatabase $db,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly Closure $newReadAuthorizer,
		private readonly int $maxPages,
	) {
	}

	/**
	 * @param int[] $pageIds
	 * @param OutputPage $listOutput Where the list renders; only links rendered there get labels.
	 */
	public function prefetch( array $pageIds, OutputPage $listOutput ): void {
		$this->listOutput = $listOutput;

		$unread = array_slice(
			array_values( array_diff( array_unique( $pageIds ), array_keys( $this->readPageIds ) ) ),
			0,
			$this->maxPages
		);

		if ( $unread === [] ) {
			return;
		}

		foreach ( $unread as $pageId ) {
			$this->readPageIds[$pageId] = true;
		}

		$published = [];

		foreach ( $this->latestRevisions( $unread ) as $latestRevision ) {
			$revision = $this->publishedRevisionToRead( $latestRevision );

			if ( $revision !== null ) {
				$published[(int)$revision->getId()] = $revision;
			}
		}

		foreach ( $this->subjectContents( $published ) as $revisionId => $content ) {
			$this->addSubjectInPlaceOfTitle( $published[$revisionId], $content );
		}
	}

	/**
	 * Only for a link rendered where the list is, and only for a reader who may read the page, since
	 * the label is its content.
	 */
	public function forListedPage( Title $title, Authority $reader, OutputPage $output ): ?Subject {
		$subject = $this->subjects[$title->getPrefixedDBkey()] ?? null;

		if ( $subject === null || $output !== $this->listOutput || !$this->mayRead( $reader, $title ) ) {
			return null;
		}

		return $subject;
	}

	private function mayRead( Authority $reader, Title $title ): bool {
		$key = $reader->getUser()->getName() . '|' . $title->getPrefixedDBkey();
		$this->readable[$key] ??= ( $this->newReadAuthorizer )( $reader )->authorizeReadByPageTitle( $title );

		return $this->readable[$key];
	}

	/**
	 * @param int[] $pageIds
	 *
	 * @return RevisionRecord[]
	 */
	private function latestRevisions( array $pageIds ): array {
		/** @var iterable<stdClass>&IResultWrapper $rows */
		$rows = $this->revisionStore->newSelectQueryBuilder( $this->db )
			->joinComment()
			->joinPage()
			->where( [ 'page_id' => $pageIds ] )
			->andWhere( 'rev_id = page_latest' )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Slot metadata only: the content is read below, for the Subject slot alone.
		$revisions = $this->revisionStore->newRevisionsFromBatch(
			$rows,
			[ 'slots' => [ MediaWikiSubjectRepository::SLOT_NAME ] ]
		)->getValue();

		return is_array( $revisions ) ? array_filter( $revisions ) : [];
	}

	/**
	 * On a wiki with an approval extension the published revision can be an older one. Its text is
	 * never hidden: the policy refuses such a revision, and it is checked here all the same, since
	 * the content is read raw.
	 */
	private function publishedRevisionToRead( RevisionRecord $latestRevision ): ?RevisionRecord {
		$published = $this->revisionPolicy->publishedRevision( $latestRevision );

		if ( $published === null
			|| $published->isDeleted( RevisionRecord::DELETED_TEXT )
			|| !$published->hasSlot( MediaWikiSubjectRepository::SLOT_NAME )
			|| $published->getSlot( MediaWikiSubjectRepository::SLOT_NAME, RevisionRecord::RAW )->getSize() > self::MAX_SLOT_SIZE
		) {
			return null;
		}

		return $published;
	}

	/**
	 * @param array<int, RevisionRecord> $revisions
	 *
	 * @return array<int, SubjectContent> Keyed by revision id
	 */
	private function subjectContents( array $revisions ): array {
		if ( $revisions === [] ) {
			return [];
		}

		$blobs = $this->revisionStore->getContentBlobsForBatch(
			array_map( static fn ( RevisionRecord $revision ): int => (int)$revision->getId(), $revisions ),
			[ MediaWikiSubjectRepository::SLOT_NAME ]
		)->getValue();

		$contents = [];

		foreach ( is_array( $blobs ) ? $blobs : [] as $revisionId => $slots ) {
			$slot = $slots[MediaWikiSubjectRepository::SLOT_NAME] ?? null;

			if ( $slot?->model_name === SubjectContent::CONTENT_MODEL_ID ) {
				$contents[(int)$revisionId] = new SubjectContent( (string)$slot->blob_data );
			}
		}

		return $contents;
	}

	private function addSubjectInPlaceOfTitle( RevisionRecord $publishedRevision, SubjectContent $content ): void {
		$title = Title::newFromPageIdentity( $publishedRevision->getPage() );

		try {
			$subject = SubjectDisplayName::inPlaceOfPageTitle( $content->getPageSubjects(), $title->getPrefixedText() );
		} catch ( Throwable ) {
			// Subject data that does not deserialize must not take the list down; its page keeps its title.
			return;
		}

		if ( $subject !== null ) {
			$this->subjects[$title->getPrefixedDBkey()] = $subject;
		}
	}

}
