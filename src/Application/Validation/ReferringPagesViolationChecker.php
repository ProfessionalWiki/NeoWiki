<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\ReferringPagesLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Checks again the pages whose Subjects relate to given Subjects, a batch at a time, as after those Subjects
 * appeared, went away or changed Schema: a relation is valid or not by whether its target exists and by
 * the target's Schema.
 */
readonly class ReferringPagesViolationChecker {

	public function __construct(
		private ReferringPagesLookup $referringPagesLookup,
		private PageViolationChecker $pageChecker,
	) {
	}

	/**
	 * @param string[] $targetIds
	 *
	 * @return int|null The page id to continue after, null when no pages remain
	 */
	public function checkBatch( array $targetIds, int $afterPageId, int $limit ): ?int {
		$pageIds = $this->referringPagesLookup->getPageIdsReferringTo( $targetIds, $afterPageId, $limit );

		foreach ( $pageIds as $pageId ) {
			$this->pageChecker->checkPage( new PageId( $pageId ) );
		}

		return count( $pageIds ) < $limit ? null : $pageIds[array_key_last( $pageIds )];
	}

}
