<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

use Wikimedia\Rdbms\IDatabase;
use TitleArray;

class DatabaseSchemaNameLookup implements SchemaNameLookup {

	private const LIMIT = 10;

	public function __construct(
		private readonly IDatabase $db
	) {
	}

	public function getFirstTenSchemaNames(): array {

		$res = $this->db->select(
			'page',
			[ 'page_id', 'page_namespace', 'page_title' ],
			[ 'page_namespace' => (int)NS_NEOWIKI_SCHEMA ],
			__METHOD__,
			[ 'ORDER BY' => 'page_id ASC', 'LIMIT' => $this::LIMIT ]
		);

		return iterator_to_array( TitleArray::newFromResult( $res ) );
	}
}
