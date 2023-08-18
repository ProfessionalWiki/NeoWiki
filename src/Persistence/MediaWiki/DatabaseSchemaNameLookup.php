<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Subject\SchemaNameLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Title;
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

	/**
	 * @return Title[]
	 */
	public function getSchemaNamesMatching( string $search ): array {
		if ( trim( $search ) !== '' ) {
			$this->searchEngine->setNamespaces( [ NeoWikiExtension::NS_SCHEMA ] );
			$this->searchEngine->setLimitOffset( self::LIMIT );

			return $this->searchEngine->extractTitles(
				$this->searchEngine->completionSearch( $search )
			);
		}

		return $this->getFirstSchemaNames();
	}

	/**
	 * @return Title[]
	 */
	private function getFirstSchemaNames(): array {
		$res = $this->db->select(
			'page',
			[ 'page_id', 'page_namespace', 'page_title' ],
			[ 'page_namespace' => NeoWikiExtension::NS_SCHEMA ],
			__METHOD__,
			[
				'ORDER BY' => 'page_id ASC',
				'LIMIT' => self::LIMIT
			]
		);

		return iterator_to_array( TitleArray::newFromResult( $res ) );
	}
}
