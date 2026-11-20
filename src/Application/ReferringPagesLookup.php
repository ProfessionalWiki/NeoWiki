<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

/**
 * The pages publishing Subjects with a relation to given Subjects.
 */
interface ReferringPagesLookup {

	/**
	 * @param string[] $targetIds
	 *
	 * @return int[] Up to $limit page ids after $afterPageId, ascending
	 */
	public function getPageIdsReferringTo( array $targetIds, int $afterPageId, int $limit ): array;

}
