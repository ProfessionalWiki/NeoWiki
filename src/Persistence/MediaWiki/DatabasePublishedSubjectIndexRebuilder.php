<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Generator;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFormatter;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\UnreadableSubjectsException;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Convergent rather than destructive: each page is reindexed in place, so the index stays readable while this
 * runs, and a page whose published Subjects cannot be read is left as it is.
 */
class DatabasePublishedSubjectIndexRebuilder {

	public const int DEFAULT_BATCH_SIZE = 200;

	private readonly DatabasePublishedSubjectIndex $index;

	public function __construct(
		private readonly IDatabase $db,
		private readonly RevisionLookup $revisionLookup,
		private readonly RevisionPolicy $revisionPolicy,
		private readonly TitleFormatter $titleFormatter,
		DependentSubjectRules $dependentSubjectRules,
		private readonly LoggerInterface $logger,
		private readonly int $batchSize,
	) {
		$this->index = new DatabasePublishedSubjectIndex( $db, $dependentSubjectRules );
	}

	/**
	 * @return Generator<int, int, mixed, int[]> Yields how many pages have been reindexed so far, once per batch, and
	 *   returns the ids of the pages left as they were because their published Subject data cannot be read.
	 */
	public function rebuild(): Generator {
		$reindexed = 0;
		$leftAsTheyWere = [];

		$pageWalks = [ $this->pageIdsHoldingSubjectSlotAfter( ... ), $this->pageIdsOfStrayRowsAfter( ... ) ];

		foreach ( $pageWalks as $pageIdsAfter ) {
			$lastPageId = 0;

			do {
				$pageIds = $pageIdsAfter( $lastPageId );

				foreach ( $pageIds as $pageId ) {
					if ( $this->reindexPage( $pageId ) ) {
						$reindexed++;
					} else {
						$leftAsTheyWere[] = $pageId;
					}

					$lastPageId = $pageId;
				}

				if ( $pageIds !== [] ) {
					yield $reindexed;
				}
			} while ( count( $pageIds ) === $this->batchSize );
		}

		return $leftAsTheyWere;
	}

	/**
	 * @return int[]
	 */
	private function pageIdsHoldingSubjectSlotAfter( int $afterPageId ): array {
		return SubjectSlotPages::pageIdsAfter( $this->db, $afterPageId, $this->batchSize );
	}

	/**
	 * The pages the first walk does not reach: deleted, or stripped of their slot, without a hook firing.
	 * Relation rows only ever come with Subject rows, so these are all the pages with stray rows.
	 *
	 * @return int[]
	 */
	private function pageIdsOfStrayRowsAfter( int $afterPageId ): array {
		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'nws_page_id' )
			->distinct()
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->where( $this->db->buildComparison( '>', [ 'nws_page_id' => $afterPageId ] ) )
			->andWhere( 'nws_page_id NOT IN (' . SubjectSlotPages::newQuery( $this->db )->getSQL() . ')' )
			->orderBy( 'nws_page_id' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	/**
	 * @return bool False when the page is left as it was
	 */
	private function reindexPage( int $pageId ): bool {
		$page = new PageId( $pageId );

		// From the primary, like the walk: a replica may not have the page's current revision yet.
		$current = $this->revisionLookup->getRevisionByPageId( $pageId, 0, IDBAccessObject::READ_LATEST );

		if ( $current === null ) {
			$this->index->removePage( $page );
			return true;
		}

		$published = $this->revisionPolicy->publishedRevision( $current );

		if ( $published === null ) {
			$this->index->removePage( $page );
			return true;
		}

		try {
			$subjects = SubjectsToPublish::of( $published );
		} catch ( UnreadableSubjectsException | RevisionAccessException $exception ) {
			$this->logger->warning(
				'NeoWiki left page {page} as it was in the published Subject index: the Subject data of its published '
				. 'revision {revision} cannot be read. {message}',
				[
					'page' => $pageId,
					'revision' => $published->getId(),
					'message' => $exception->getMessage(),
					'exception' => $exception,
				]
			);
			return false;
		}

		$this->index->setSubjectsOfPage(
			$page,
			$this->titleFormatter->getPrefixedText( $current->getPage() ),
			$subjects,
			(string)$published->getTimestamp()
		);

		return true;
	}

}
