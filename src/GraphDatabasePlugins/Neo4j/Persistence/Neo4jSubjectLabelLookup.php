<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

class Neo4jSubjectLabelLookup implements SubjectLabelLookup {

	/**
	 * Rows are read past the requested limit so that dropping Subjects on pages the caller cannot
	 * read still tends to fill it. A heuristic, not a guarantee: a run of unreadable Subjects longer
	 * than the extra headroom can still shorten the result.
	 */
	private const int OVERFETCH_FACTOR = 4;

	public function __construct(
		private readonly ClientInterface $client,
		private readonly string $wikiId,
		private readonly PageReadAuthorizer $readAuthorizer,
	) {
	}

	/**
	 * @return SubjectLabelLookupResult[]
	 */
	public function getSubjectLabelsMatching( string $search, int $limit, string $schemaName ): array {
		if ( trim( $search ) === '' ) {
			return [];
		}

		$results = [];

		// Authorize rows one at a time and stop once the limit is filled, so when readable rows are
		// plentiful the expensive per-page check runs about `limit` times rather than across the
		// whole over-fetched window. A mostly-unreadable search still checks the whole window.
		foreach ( $this->fetchLabels( $search, $limit * self::OVERFETCH_FACTOR, $schemaName ) as $row ) {
			if ( count( $results ) >= $limit ) {
				break;
			}

			if ( $this->readAuthorizer->authorizeReadByPageId( new PageId( $row['pageId'] ) ) ) {
				$results[] = new SubjectLabelLookupResult( id: $row['id'], label: $row['name'] );
			}
		}

		return $results;
	}

	/**
	 * The Subject is reached through a Page owned by the current wiki, so the caller can check
	 * per-page read access and the returned page id always resolves within this wiki (page ids
	 * are unique only per wiki).
	 *
	 * @return list<array{id: string, name: string, pageId: int}>
	 */
	private function fetchLabels( string $search, int $limit, string $schemaName ): array {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $search, $limit, $schemaName ): array {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					"MATCH (page:Page { wiki_id: \$wikiId })-[:HasSubject]->(n:Subject)
					 WHERE toLower(n.name) STARTS WITH toLower(\$search)
					 AND \$schemaName IN labels(n)
					 AND n.wiki_id = \$wikiId
					 RETURN n.id AS id, n.name AS name, page.id AS pageId
					 ORDER BY n.name
					 LIMIT \$limit",
					[
						'search' => $search,
						'limit' => $limit,
						'schemaName' => $schemaName,
						'wikiId' => $this->wikiId,
					]
				);

				$rows = [];
				foreach ( $result as $row ) {
					$rows[] = [
						'id' => $row->get( 'id' ),
						'name' => $row->get( 'name' ),
						'pageId' => (int)$row->get( 'pageId' ),
					];
				}

				return $rows;
			}
		);
	}

}
