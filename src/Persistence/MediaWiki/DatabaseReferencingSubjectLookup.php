<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\ReferencingSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Ordered by name, with generated names after the chosen ones and among themselves by Schema name: a reader sees a
 * generated name as the Subject id, which would look out of place amid the names.
 */
class DatabaseReferencingSubjectLookup implements ReferencingSubjectLookup {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	/**
	 * @return SubjectId[]
	 */
	public function getIdsOfSubjectsReferencing( SubjectId $target, int $limit ): array {
		$db = $this->connectionProvider->getReplicaDatabase();

		$result = $db->newSelectQueryBuilder()
			->select( [
				'nwr_subject_id',
				'name_is_generated' => $db->conditional( [ 'nws_display_name_key' => null ], '1', '0' ),
				'sort_name' => 'COALESCE(nws_display_name_key, nws_schema)',
			] )
			->distinct()
			->from( DatabasePublishedSubjectIndex::RELATION_TABLE )
			->join(
				DatabasePublishedSubjectIndex::SUBJECT_TABLE,
				'subject',
				[ 'subject.nws_page_id = nwr_page_id', 'subject.nws_subject_id = nwr_subject_id' ]
			)
			->where( [ 'nwr_target_id' => $target->text ] )
			->andWhere( $db->expr( 'nwr_subject_id', '!=', $target->text ) )
			->andWhere( LowestPageRows::condition( $db, 'subject' ) )
			->orderBy( [ 'name_is_generated', 'sort_name', 'nwr_subject_id' ] )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ids = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			$ids[] = new SubjectId( (string)$row->nwr_subject_id );
		}

		return $ids;
	}

}
