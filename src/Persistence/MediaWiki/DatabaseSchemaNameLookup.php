<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\SchemaNameLookup;
use RuntimeException;
use SearchEngine;
use SearchSuggestion;
use SearchSuggestionSet;
use TitleValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class DatabaseSchemaNameLookup implements SchemaNameLookup {

	public function __construct(
		private readonly IDatabase $db,
		private readonly SearchEngine $searchEngine
	) {
	}

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit = 10, int $offset = 0 ): array {
		if ( trim( $search ) === '' ) {
			return $this->getFirstSchemaNames( $limit, $offset );
		}

		return $this->searchSuggestionsToTitleArray( $this->getSearchSuggestions( $search, $limit, $offset ) );
	}

	public function getSchemaCount(): int {
		/** @var string $count */
		$count = $this->db->selectField(
			'page',
			'COUNT(*)',
			[ 'page_namespace' => NeoWikiExtension::NS_SCHEMA ],
			__METHOD__
		);

		return (int)$count;
	}

	private function getSearchSuggestions( string $search, int $limit, int $offset ): SearchSuggestionSet {
		$this->searchEngine->setNamespaces( [ NeoWikiExtension::NS_SCHEMA ] );
		$this->searchEngine->setLimitOffset( $limit, $offset );

		return $this->searchEngine->completionSearch( $search );
	}

	/**
	 * @return TitleValue[]
	 */
	private function searchSuggestionsToTitleArray( SearchSuggestionSet $suggestions ): array {
		return $suggestions->map( function ( SearchSuggestion $suggestion ) {
			$title = $suggestion->getSuggestedTitle();

			if ( $title === null ) {
				throw new RuntimeException( 'Title is null' );
			}

			return new TitleValue( $title->getNamespace(), $title->getText() );
		} );
	}

	/**
	 * @return TitleValue[]
	 */
	private function getFirstSchemaNames( int $limit, int $offset ): array {
		$res = $this->db->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => NeoWikiExtension::NS_SCHEMA ],
			__METHOD__,
			[
				'ORDER BY' => 'page_id ASC',
				'LIMIT' => $limit,
				'OFFSET' => $offset,
			]
		);

		return $this->dbResultToTitleValueArray( $res );
	}

	/**
	 * @return TitleValue[]
	 */
	private function dbResultToTitleValueArray( IResultWrapper $result ): array {
		$titles = [];

		foreach ( $result as $row ) {
			$titles[] = new TitleValue( NeoWikiExtension::NS_SCHEMA, $row->page_title );
		}

		return $titles;
	}

}
