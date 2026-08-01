<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIdsLookup;

/**
 * The pages a rebuild walks, without a wiki behind them, for the cases the real lookup cannot produce
 * on demand — such as a page the walk finds but that no longer carries a Subject by the time the
 * rebuild reaches it.
 */
class InMemorySubjectPageIdsLookup implements SubjectPageIdsLookup {

	/**
	 * @var int[]
	 */
	private array $pageIds;

	public function __construct( int ...$pageIds ) {
		$this->pageIds = $pageIds;
	}

	public function getSubjectPageIdsAfter( int $afterPageId, int $limit ): array {
		$remaining = array_filter( $this->pageIds, static fn ( int $pageId ): bool => $pageId > $afterPageId );

		return array_slice( array_values( $remaining ), 0, $limit );
	}

	public function countSubjectPages(): int {
		return count( $this->pageIds );
	}

}
