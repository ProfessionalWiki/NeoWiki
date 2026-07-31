<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;

class Neo4jPageIdentifiersLookup implements PageIdentifiersLookup {

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function getPageIdOfSubject( SubjectId $subjectId ): ?PageIdentifiers {
		return $this->getPageIdsOfSubjects( new SubjectIdList( [ $subjectId ] ) )[$subjectId->text] ?? null;
	}

	/**
	 * Pages are reached by traversing HasSubject from globally-unique Subject ids, so the result is
	 * unambiguous without wiki-scoping: a Subject is only ever linked to its own wiki's page node.
	 *
	 * @return array<string, PageIdentifiers>
	 */
	public function getPageIdsOfSubjects( SubjectIdList $subjectIds ): array {
		$ids = $subjectIds->asStringArray();

		if ( $ids === [] ) {
			return [];
		}

		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $ids ): array {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					'
					MATCH (page:Page)-[:HasSubject]->(subject:Subject)
					WHERE subject.id IN $subjectIds
					RETURN subject.id AS subjectId, page.id AS id, page.name AS name, page.namespaceId AS namespaceId',
					[ 'subjectIds' => $ids ]
				);

				return $this->newPageIdentifiersMap( $result->getResults()->toRecursiveArray() );
			}
		);
	}

	/**
	 * @param array<mixed> $rows
	 * @return array<string, PageIdentifiers>
	 */
	private function newPageIdentifiersMap( array $rows ): array {
		$pageIdentifiers = [];

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && $this->hasAllColumns( $row ) ) {
				$pageIdentifiers[(string)$row['subjectId']] = new PageIdentifiers(
					id: new PageId( (int)$row['id'] ),
					title: $row['name'],
					namespaceId: (int)$row['namespaceId'],
				);
			}
		}

		return $pageIdentifiers;
	}

	/**
	 * @param array<mixed> $row
	 */
	private function hasAllColumns( array $row ): bool {
		foreach ( [ 'subjectId', 'id', 'name', 'namespaceId' ] as $column ) {
			if ( !array_key_exists( $column, $row ) ) {
				return false;
			}
		}

		return true;
	}

}
