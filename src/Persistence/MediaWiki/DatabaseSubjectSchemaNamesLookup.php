<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Application\SubjectSchemaNamesLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use stdClass;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Reads the database it is given, the primary where the answer must match what a write in progress replaces.
 */
class DatabaseSubjectSchemaNamesLookup implements SubjectSchemaNamesLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
	) {
	}

	public function getSchemaNamesOfSubjectsOn( PageId $pageId ): array {
		$schemaNames = [];

		$rows = $this->db->newSelectQueryBuilder()
			->select( [ 'nws_subject_id', 'nws_schema' ] )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->where( [ 'nws_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		/** @var stdClass $row */
		foreach ( $rows as $row ) {
			$schemaNames[(string)$row->nws_subject_id] = (string)$row->nws_schema;
		}

		return $schemaNames;
	}

}
