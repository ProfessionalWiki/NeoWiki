<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Permissions\Authority;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\SchemaNameLookup;
use RuntimeException;
use SearchEngine;
use SearchSuggestion;
use SearchSuggestionSet;
use MediaWiki\Title\TitleValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class DatabaseSchemaNameLookup implements SchemaNameLookup {

	public function __construct(
		private readonly IDatabase $db,
		private readonly SearchEngine $searchEngine,
		private readonly Authority $authority,
		private readonly TitleFactory $titleFactory,
	) {
	}

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit, int $offset = 0 ): array {
		if ( trim( $search ) === '' ) {
			return $this->filterReadable( $this->getFirstSchemaNames( $limit, $offset ) );
		}

		return $this->filterReadable(
			$this->searchSuggestionsToTitleArray( $this->getSearchSuggestions( $search, $limit, $offset ) )
		);
	}

	/**
	 * The search engine applies its own visibility rules inconsistently across engines, and the
	 * raw DB branch applies none, so both branches share this binding per-title read filter.
	 * Filtered names are simply absent, like Schemas that do not exist (#1046).
	 *
	 * @param TitleValue[] $titles
	 * @return TitleValue[]
	 */
	private function filterReadable( array $titles ): array {
		return array_values( array_filter(
			$titles,
			fn ( TitleValue $titleValue ): bool =>
				$this->authority->authorizeRead( 'read', $this->titleFactory->newFromLinkTarget( $titleValue ) )
		) );
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
