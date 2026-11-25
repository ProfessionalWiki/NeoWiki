<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Generator;
use ProfessionalWiki\NeoWiki\Application\Validation\PageViolationChecker;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use Wikimedia\Rdbms\IDatabase;

/**
 * Checks every page holding Subjects, then every page the Violation index still has rows for, in page id
 * order, a batch at a time.
 */
class DatabaseViolationIndexRebuilder {

	public const int DEFAULT_BATCH_SIZE = 100;

	public function __construct(
		private readonly IDatabase $db,
		private readonly PageViolationChecker $checker,
		private readonly int $batchSize,
	) {
	}

	/**
	 * @return Generator<int, int> Yields how many pages have been checked so far, once per batch
	 */
	public function rebuild(): Generator {
		$checked = 0;
		$position = [ 'walk' => 0, 'afterPageId' => 0 ];

		do {
			$batch = $this->checkBatch( $position['walk'], $position['afterPageId'] );
			$checked += $batch['checked'];

			if ( $batch['checked'] > 0 ) {
				yield $checked;
			}

			$position = $batch['next'];
		} while ( $position !== null );
	}

	/**
	 * Checks the next batch of pages: in walk 0 the pages holding Subjects, in walk 1 the pages with rows left
	 * after their Subjects went without a hook firing.
	 *
	 * @return array{checked: int, next: array{walk: int, afterPageId: int}|null} How many pages it checked, and
	 *   where the next batch starts, null after the last
	 */
	public function checkBatch( int $walk, int $afterPageId ): array {
		$pageIds = $walk === 0 ? $this->pageIdsHoldingSubjectSlotAfter( $afterPageId ) : $this->pageIdsOfStrayRowsAfter( $afterPageId );

		foreach ( $pageIds as $pageId ) {
			$this->checker->checkPage( new PageId( $pageId ) );
		}

		return [ 'checked' => count( $pageIds ), 'next' => $this->nextPosition( $walk, $pageIds ) ];
	}

	/**
	 * @param int[] $checkedPageIds
	 * @return array{walk: int, afterPageId: int}|null
	 */
	private function nextPosition( int $walk, array $checkedPageIds ): ?array {
		if ( count( $checkedPageIds ) === $this->batchSize ) {
			return [ 'walk' => $walk, 'afterPageId' => $checkedPageIds[array_key_last( $checkedPageIds )] ];
		}

		return $walk === 0 ? [ 'walk' => 1, 'afterPageId' => 0 ] : null;
	}

	/**
	 * @return int[]
	 */
	private function pageIdsHoldingSubjectSlotAfter( int $afterPageId ): array {
		return SubjectSlotPages::pageIdsAfter( $this->db, $afterPageId, $this->batchSize );
	}

	/**
	 * The pages the first walk does not reach: deleted, or stripped of their slot, without a hook firing.
	 *
	 * @return int[]
	 */
	private function pageIdsOfStrayRowsAfter( int $afterPageId ): array {
		return array_map( 'intval', $this->db->newSelectQueryBuilder()
			->select( 'nwv_page_id' )
			->distinct()
			->from( DatabaseViolationIndex::TABLE )
			->where( $this->db->buildComparison( '>', [ 'nwv_page_id' => $afterPageId ] ) )
			->andWhere( 'nwv_page_id NOT IN (' . SubjectSlotPages::newQuery( $this->db )->getSQL() . ')' )
			->orderBy( 'nwv_page_id' )
			->limit( $this->batchSize )
			->caller( __METHOD__ )
			->fetchFieldValues() );
	}

}
