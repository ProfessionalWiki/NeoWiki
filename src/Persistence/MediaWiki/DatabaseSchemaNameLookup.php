<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
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

	public const int READABLE_NAMES_BATCH_SIZE = 100;

	public function __construct(
		private readonly IDatabase $db,
		private readonly SearchEngine $searchEngine,
		private readonly PageReadAuthorizer $readAuthorizer,
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
			fn ( TitleValue $titleValue ): bool => $this->readAuthorizer->authorizeReadByPageTitle(
				$this->titleFactory->newFromLinkTarget( $titleValue )
			)
		) );
	}

	/**
	 * Keyset pagination over the page title: each batch seeks past the last seen title instead of
	 * re-walking the namespace, so a request costs the batches it consumes, not the whole
	 * namespace, and pages stay stable when Schemas are created or deleted between requests.
	 * Unreadable Schemas are never yielded, so a cursor built from the yielded keys pages over
	 * readable Schemas only (#1062).
	 *
	 * @return iterable<string, TitleValue> Readable Schema names keyed by database key, in name order.
	 */
	public function getReadableSchemaNames( string $search, string $afterName ): iterable {
		$searchKey = self::searchKey( $search );
		$lastName = $afterName;

		do {
			$res = $this->db->select(
				'page',
				[ 'page_title' ],
				[
					'page_namespace' => NeoWikiExtension::NS_SCHEMA,
					$this->db->expr( 'page_title', '>', $lastName ),
				],
				__METHOD__,
				[
					'ORDER BY' => 'page_title ASC',
					'LIMIT' => self::READABLE_NAMES_BATCH_SIZE,
				]
			);

			foreach ( $res as $row ) {
				$lastName = (string)$row->page_title;
				$title = new TitleValue( NeoWikiExtension::NS_SCHEMA, $lastName );

				if ( self::nameContains( $lastName, $searchKey ) && $this->isReadable( $title ) ) {
					yield $lastName => $title;
				}
			}
		} while ( $res->numRows() === self::READABLE_NAMES_BATCH_SIZE );
	}

	/**
	 * The search in database key form, where a title holds each run of spaces as one underscore.
	 */
	private static function searchKey( string $search ): string {
		return trim( (string)preg_replace( '/[\s_]+/u', '_', $search ), '_' );
	}

	/**
	 * Matched here because the title column is binary and SQL has no portable case-insensitive match
	 * on it; a wiki holds hundreds of Schemas, so scanning their names stays cheap.
	 */
	private static function nameContains( string $name, string $searchKey ): bool {
		return $searchKey === '' || mb_stripos( $name, $searchKey ) !== false;
	}

	private function isReadable( TitleValue $title ): bool {
		return $this->readAuthorizer->authorizeReadByPageTitle( $this->titleFactory->newFromLinkTarget( $title ) );
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
