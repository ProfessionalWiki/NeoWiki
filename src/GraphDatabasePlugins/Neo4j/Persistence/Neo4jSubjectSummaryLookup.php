<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Types\CypherMap;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SortDirection;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaries;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryCursor;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummaryQuery;
use ProfessionalWiki\NeoWiki\Application\SubjectSummaries\SubjectSummarySort;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

/**
 * Lists this wiki's Subjects from the Neo4j projection. Rows on pages the caller may not read are dropped as they
 * are reached (ADR 27), so a request reads on past them to fill its page, up to the scan bound. Reaching the bound
 * shortens the listing, as in Neo4jSubjectLabelLookup: the rows found so far come without a next cursor, so a page
 * cut short by hidden rows reads as the end of the listing.
 *
 * The node `name` is SubjectDisplayName::labelOrPageName, absent when that is null, so it is the chosen name as is.
 * Every fetch but newest first over every Schema reads every Subject in scope, found by label: the Schema's when one
 * is given.
 */
readonly class Neo4jSubjectSummaryLookup implements SubjectSummaryLookup {

	public function __construct(
		private ClientInterface $client,
		private string $wikiId,
		private PageReadAuthorizer $readAuthorizer,
		/** The most rows one request reads looking for readable ones. Reaching it ends the listing. */
		private int $scanBound,
	) {
	}

	public function getSubjectSummaries( SubjectSummaryQuery $query ): SubjectSummaries {
		$summaries = [];
		$lastServed = null;
		$position = $query->after;
		$scanned = 0;
		$requested = min( ( $query->limit + 1 ) * 4, $this->scanBound );
		$readablePages = [];

		// The first fetch expects to fill the page; when unreadable rows keep it short, a second fetch reads the rest
		// of the bound. So a request makes at most two queries and reads at most the bound.
		while ( $requested > 0 ) {
			$rows = $this->fetchRows( $query, $position, $requested );

			foreach ( $rows as $row ) {
				$scanned++;
				$position = $row['cursor'];

				if ( !$this->mayRead( $row['summary']->pageId, $readablePages ) ) {
					continue;
				}

				// A readable row past a full page is what says there is a next page.
				if ( count( $summaries ) === $query->limit ) {
					return new SubjectSummaries( $summaries, $lastServed );
				}

				$summaries[] = $row['summary'];
				$lastServed = $row['cursor'];
			}

			if ( count( $rows ) < $requested ) {
				break;
			}

			$requested = $this->scanBound - $scanned;
		}

		// The listing ended, or the scan bound shortened it.
		return new SubjectSummaries( $summaries, null );
	}

	/**
	 * @param array<int, bool> $readablePages Page id → readable, filled as pages are reached.
	 */
	private function mayRead( int $pageId, array &$readablePages ): bool {
		$readablePages[$pageId] ??= $this->readAuthorizer->authorizeReadByPageId( new PageId( $pageId ) );

		return $readablePages[$pageId];
	}

	/**
	 * @return list<array{summary: SubjectSummary, cursor: SubjectSummaryCursor}>
	 */
	private function fetchRows( SubjectSummaryQuery $query, ?SubjectSummaryCursor $after, int $limit ): array {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $query, $after, $limit ): array {
				$result = $transaction->run(
					$this->cypher( $query, $after ),
					[
						'wikiId' => $this->wikiId,
						'schemaName' => $query->schemaName?->getText(),
						'search' => $query->search,
						'afterValue' => $after?->sortValue,
						'afterId' => $after?->subjectId,
						'limit' => $limit,
					]
				);

				$rows = [];

				foreach ( $result as $row ) {
					$rows[] = $this->toRow( $row, $query );
				}

				return $rows;
			}
		);
	}

	/**
	 * A Subject that several pages hold (ADR 32 duplicates) is listed once, under the lowest page id. Stub nodes have
	 * no page, so they are never listed.
	 */
	private function cypher( SubjectSummaryQuery $query, ?SubjectSummaryCursor $after ): string {
		$sortValue = match ( $query->sort ) {
			SubjectSummarySort::Newest => 'null',
			SubjectSummarySort::Name => 'toLower(subject.name)',
			SubjectSummarySort::Schema => 'toLower(schemaName)',
			SubjectSummarySort::Page => 'toLower(page.name)',
			SubjectSummarySort::Edited => 'lastEdited',
		};

		return "
			{$this->subjectsWithTheirPages( $query, $after )}
			WITH subject, page, head([label IN labels(subject) WHERE label <> 'Subject']) AS schemaName,
				coalesce(page.lastUpdated.epochSeconds, 0) AS lastEdited
			WHERE \$search = ''
				OR toLower(subject.name) CONTAINS toLower(\$search)
				OR toLower(page.name) CONTAINS toLower(\$search)
				OR subject.id STARTS WITH \$search
			WITH subject, page, schemaName, lastEdited, $sortValue AS sortValue
			WHERE {$this->afterCondition( $query, $after )}
			RETURN subject.id AS id, subject.name AS name, schemaName, page.id AS pageId, page.name AS pageTitle,
				lastEdited, sortValue
			ORDER BY {$this->orderBy( $query )}
			LIMIT \$limit";
	}

	/**
	 * Newest first over every Schema walks the Subject id index from the cursor on and stops at the limit. Neo4j cannot
	 * tell a rare Schema from a common one, so with a Schema such a walk could cover the whole index. A column sort
	 * reads every Subject anyway, and with the walk's subquery Neo4j sorts them all in memory rather than keeping only
	 * the rows it returns.
	 */
	private function subjectsWithTheirPages( SubjectSummaryQuery $query, ?SubjectSummaryCursor $after ): string {
		if ( $query->sort === SubjectSummarySort::Newest && $query->schemaName === null ) {
			$idCondition = $after === null ? 'subject.id IS NOT NULL' : 'subject.id < $afterId';

			return "
				MATCH (subject:Subject {wiki_id: \$wikiId})
				WHERE $idCondition
				CALL (subject) {
					MATCH (page:Page {wiki_id: \$wikiId})-[:HasSubject]->(subject)
					RETURN page ORDER BY page.id LIMIT 1
				}";
		}

		// A dynamic label takes the Schema name as a parameter, so no name can change the query.
		$subjectLabels = $query->schemaName === null ? 'Subject' : 'Subject:$($schemaName)';

		return "
			MATCH (page:Page {wiki_id: \$wikiId})-[:HasSubject]->(subject:$subjectLabels {wiki_id: \$wikiId})
			WITH subject, min(page.id) AS pageId
			MATCH (page:Page {wiki_id: \$wikiId, id: pageId})";
	}

	private function orderBy( SubjectSummaryQuery $query ): string {
		$direction = $query->direction === SortDirection::Ascending ? 'ASC' : 'DESC';

		return match ( $query->sort ) {
			SubjectSummarySort::Newest => 'id DESC',
			// Generated names after chosen ones, whichever way the names run.
			SubjectSummarySort::Name => "sortValue IS NULL, sortValue $direction, id DESC",
			default => "sortValue $direction, id DESC",
		};
	}

	/**
	 * Keyset pagination: the rows that come after the cursor's row in the order above.
	 */
	private function afterCondition( SubjectSummaryQuery $query, ?SubjectSummaryCursor $after ): string {
		if ( $after === null ) {
			return 'true';
		}

		if ( $query->sort === SubjectSummarySort::Newest ) {
			return 'subject.id < $afterId';
		}

		$beyond = $query->direction === SortDirection::Ascending ? '>' : '<';
		$afterInOrder = "(sortValue $beyond \$afterValue OR (sortValue = \$afterValue AND subject.id < \$afterId))";

		if ( $query->sort !== SubjectSummarySort::Name ) {
			return $afterInOrder;
		}

		return $after->sortValue === null
			? '(sortValue IS NULL AND subject.id < $afterId)'
			: "(sortValue IS NULL OR $afterInOrder)";
	}

	/**
	 * @return array{summary: SubjectSummary, cursor: SubjectSummaryCursor}
	 */
	private function toRow( CypherMap $row, SubjectSummaryQuery $query ): array {
		$id = (string)$row->get( 'id' );
		$name = $row->get( 'name' );
		$schemaName = (string)$row->get( 'schemaName' );

		return [
			'summary' => new SubjectSummary(
				subjectId: $id,
				displayName: is_string( $name ) ? $name : $schemaName,
				displayNameIsGenerated: !is_string( $name ),
				schemaName: $schemaName,
				pageId: (int)$row->get( 'pageId' ),
				pageTitle: (string)$row->get( 'pageTitle' ),
				lastEdited: gmdate( 'Y-m-d\TH:i:s\Z', (int)$row->get( 'lastEdited' ) ),
			),
			'cursor' => new SubjectSummaryCursor( $query->sort, $query->direction, $row->get( 'sortValue' ), $id ),
		];
	}

}
