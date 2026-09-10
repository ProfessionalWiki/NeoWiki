<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Rebuilds `neowiki_subject_page` from the subject slots, which are what it derives from.
 *
 * Convergent rather than destructive: every page is reindexed in place from what it currently holds, so
 * nothing stops resolving while it runs, and a page whose Subjects cannot be read is left exactly as it
 * is. This is the repair path for a history merge that leaves the source page as a redirect, which
 * writes that revision without firing a hook the index is built on.
 */
class DatabaseSubjectPageIndexRebuilder {

	public const int DEFAULT_BATCH_SIZE = 500;

	private readonly DatabaseSubjectPageIndex $index;

	public function __construct(
		private readonly IDatabase $db,
		private readonly RevisionLookup $revisionLookup,
		private readonly int $batchSize,
	) {
		$this->index = new DatabaseSubjectPageIndex( $db );
	}

	/**
	 * @return iterable<int> How many pages have been reindexed so far, once per batch.
	 */
	public function rebuild(): iterable {
		$this->removeRowsOfPagesHoldingNoSubjectSlot();

		$lastPageId = 0;
		$indexed = 0;

		do {
			$pageIds = $this->pageIdsHoldingSubjectSlotAfter( $lastPageId );

			if ( $pageIds === [] ) {
				return;
			}

			foreach ( $pageIds as $pageId ) {
				$lastPageId = $pageId;
				$subjectHeaders = $this->subjectHeadersOfPage( $pageId );

				if ( $subjectHeaders !== null ) {
					$this->index->setSubjectsOfPage( new PageId( $pageId ), $subjectHeaders );
					$indexed++;
				}
			}

			yield $indexed;
		} while ( count( $pageIds ) === $this->batchSize );
	}

	/**
	 * Covers both a page that has been deleted and one whose slot an unhooked write removed: neither is
	 * reached by the walk below, so neither would have its rows replaced.
	 */
	private function removeRowsOfPagesHoldingNoSubjectSlot(): void {
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( DatabaseSubjectPageIndex::TABLE )
			->where( 'nwsp_page_id NOT IN (' . $this->pagesHoldingSubjectSlot()->getSQL() . ')' )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @return int[]
	 */
	private function pageIdsHoldingSubjectSlotAfter( int $afterPageId ): array {
		return array_map( 'intval', $this->pagesHoldingSubjectSlot()
			->where( $this->db->buildComparison( '>', [ 'page_id' => $afterPageId ] ) )
			->orderBy( 'page_id' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

	/**
	 * The pages whose current revision has a subject slot. Every other page holds no Subjects, so the
	 * index has nothing to say about it. The caller is left unset, so that embedding this as a subquery
	 * marks it as one rather than reporting it as a query of its own.
	 */
	private function pagesHoldingSubjectSlot(): SelectQueryBuilder {
		return $this->db->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->join( 'slots', null, 'slot_revision_id = page_latest' )
			->join( 'slot_roles', null, 'slot_role_id = role_id' )
			->where( [ 'role_name' => MediaWikiSubjectRepository::SLOT_NAME ] );
	}

	/**
	 * @return SubjectHeader[]|null Null when the page has to be left alone: its subject slot holds content that
	 *   is not Subject data, so what it holds cannot be read, and reindexing it as holding nothing would
	 *   drop the Subjects it does hold. The hook path skips such a page for the same reason.
	 */
	private function subjectHeadersOfPage( int $pageId ): ?array {
		// Read from the primary, like the walk that named the page: on a replica the page may not have
		// its current revision yet, and indexing it from a stale one would file the wrong Subjects.
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId, 0, IDBAccessObject::READ_LATEST );

		// Re-checked rather than taken from the walk that named the page: an edit between the two can
		// have left the page without the slot, and asking a revision for a slot it lacks throws.
		if ( $revision === null || !$revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
			return [];
		}

		// Read past revision deletion: what a page holds is what the index answers with, and hiding a
		// revision from readers does not move its Subjects to another page.
		$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME, RevisionRecord::RAW );

		if ( !$content instanceof SubjectContent ) {
			return null;
		}

		return $content->getSubjectHeaders();
	}

}
