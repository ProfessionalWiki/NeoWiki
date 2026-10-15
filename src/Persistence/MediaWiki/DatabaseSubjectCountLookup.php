<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\SubjectCountLookup;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * A Subject that several pages hold counts once under each Schema those pages give it, which is once unless the
 * pages disagree.
 */
class DatabaseSubjectCountLookup implements SubjectCountLookup {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getSubjectCountsBySchema(): array {
		$result = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [ 'nws_schema', 'subject_count' => 'COUNT(DISTINCT nws_subject_id)' ] )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->groupBy( 'nws_schema' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$counts = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			$counts[(string)$row->nws_schema] = (int)$row->subject_count;
		}

		return $counts;
	}

}
