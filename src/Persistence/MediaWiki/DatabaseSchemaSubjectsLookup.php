<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\SchemaSubjectsLookup;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;

class DatabaseSchemaSubjectsLookup implements SchemaSubjectsLookup {

	private const int NAME_COLUMN_BYTES = 255;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	public function getSubjectsAfter( string $schemaName, string $afterSubjectId, int $afterPageId, int $limit ): array {
		$db = $this->connectionProvider->getReplicaDatabase();

		$rows = $db->newSelectQueryBuilder()
			->select( [ 'nws_subject_id', 'nws_page_id' ] )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->where( [ 'nws_schema' => mb_strcut( $schemaName, 0, self::NAME_COLUMN_BYTES, 'UTF-8' ) ] )
			->andWhere( $db->buildComparison( '>', [ 'nws_subject_id' => $afterSubjectId, 'nws_page_id' => $afterPageId ] ) )
			->orderBy( [ 'nws_subject_id', 'nws_page_id' ] )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$subjects = [];

		/** @var stdClass $row */
		foreach ( $rows as $row ) {
			$subjects[] = [ 'subjectId' => (string)$row->nws_subject_id, 'pageId' => (int)$row->nws_page_id ];
		}

		return $subjects;
	}

}
