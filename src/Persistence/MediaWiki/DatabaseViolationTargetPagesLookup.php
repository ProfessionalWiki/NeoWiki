<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\ReferringPagesLookup;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * The pages recorded with Violations about given relation targets: the only ones a target that appears can
 * change, as their relations to it were recorded as not found.
 */
class DatabaseViolationTargetPagesLookup implements ReferringPagesLookup {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getPageIdsReferringTo( array $targetIds, int $afterPageId, int $limit ): array {
		if ( $targetIds === [] ) {
			return [];
		}

		$db = $this->connectionProvider->getReplicaDatabase();

		return array_map(
			'intval',
			$db->newSelectQueryBuilder()
				->select( 'nwv_page_id' )
				->distinct()
				->from( DatabaseViolationIndex::TABLE )
				->where( [ 'nwv_target_id' => $targetIds ] )
				->andWhere( $db->buildComparison( '>', [ 'nwv_page_id' => $afterPageId ] ) )
				->orderBy( 'nwv_page_id' )
				->limit( $limit )
				->caller( __METHOD__ )
				->fetchFieldValues()
		);
	}

}
