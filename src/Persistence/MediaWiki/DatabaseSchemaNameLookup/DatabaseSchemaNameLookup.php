<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSchemaNameLookup;

use Wikimedia\Rdbms\IDatabase;
use TitleArray;
use SearchEngine;

class DatabaseSchemaNameLookup implements SchemaNameLookup {

	private const LIMIT = 10;

	public function __construct(
		private readonly IDatabase $db,
		private readonly SearchEngine $searchEngine
	) {
	}

	public function getFirstSchemaNames(): array {
		$res = $this->db->select(
			'page',
			[ 'page_id', 'page_namespace', 'page_title' ],
			[ 'page_namespace' => (int)NS_NEOWIKI_SCHEMA ],
			__METHOD__,
			[ 'ORDER BY' => 'page_id ASC', 'LIMIT' => $this::LIMIT ]
		);

		return iterator_to_array( TitleArray::newFromResult( $res ) );
	}

	public function getSchemaNamesMatching( string $search ): array {
		if ( $search ) {
			$this->searchEngine->setNamespaces( [ (int)NS_NEOWIKI_SCHEMA ] );
			$this->searchEngine->setLimitOffset( (int)$this::LIMIT );

			return $this->searchEngine->extractTitles(
				$this->searchEngine->completionSearch( $search )
			);
		}

		return $this->getFirstSchemaNames();
	}
}
