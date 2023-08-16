<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

use Wikimedia\Rdbms\IDatabase;

class DatabaseSchemaNameLookup implements SchemaNameLookup {

	public function __construct(
		private readonly IDatabase $db,
		private readonly ResultWrapperToArrayConverter $resultConverter
	) {
	}

	public function getFirstTenSchemaNamesMatching( string $search = '' ): array {
		$search = addslashes( str_replace( ';', '', $search ) );
		$likeQuery = '(`page_title` LIKE \'%' . $search . '%\' OR `page_title` LIKE \'%' . ucfirst( $search ) . '%\')';

		$resultWrapper = $this->db->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace = ' . (int)NS_NEOWIKI_SCHEMA . ' AND ' . $likeQuery ],
			__METHOD__,
			[ 'ORDER BY' => 'page_id ASC', 'LIMIT' => 10 ]
		);

		return $this->resultConverter->convertToObjectArray( $resultWrapper );
	}
}
