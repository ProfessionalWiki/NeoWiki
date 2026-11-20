<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\ReferringPagesLookup;
use Wikimedia\Rdbms\IConnectionProvider;

class DatabaseReferringPagesLookup implements ReferringPagesLookup {

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
				->select( 'nwr_page_id' )
				->distinct()
				->from( DatabasePublishedSubjectIndex::RELATION_TABLE )
				->where( [ 'nwr_target_id' => $targetIds ] )
				->andWhere( $db->buildComparison( '>', [ 'nwr_page_id' => $afterPageId ] ) )
				->orderBy( 'nwr_page_id' )
				->limit( $limit )
				->caller( __METHOD__ )
				->fetchFieldValues()
		);
	}

}
