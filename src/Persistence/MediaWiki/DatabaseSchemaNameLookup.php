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

	private const LIMIT = 10;

	public function __construct(
		private readonly IDatabase $db,
		private readonly SearchEngine $searchEngine
	) {
	}

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search ): array {
		if ( trim( $search ) === '' ) {
			return $this->getFirstSchemaNames();
		}

		return $this->searchSuggestionsToTitleArray( $this->getSearchSuggestions( $search ) );
	}

	private function getSearchSuggestions( string $search ): SearchSuggestionSet {
		$this->searchEngine->setNamespaces( [ NeoWikiExtension::NS_SCHEMA ] );
		$this->searchEngine->setLimitOffset( self::LIMIT );

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
	private function getFirstSchemaNames(): array {
		$res = $this->db->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => NeoWikiExtension::NS_SCHEMA ],
			__METHOD__,
			[
				'ORDER BY' => 'page_id ASC',
				'LIMIT' => self::LIMIT
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
