<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;

class Neo4jSubjectLabelLookup implements SubjectLabelLookup {

	public function __construct(
		private readonly ClientInterface $client
	) {
	}

	/**
	 * @return SubjectLabelLookupResult[]
	 */
	public function getSubjectLabelsMatching( string $search, int $limit, array $schemaNames = [] ): array {
		if ( trim( $search ) === '' ) {
			return [];
		}

		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $search, $limit, $schemaNames ): array {
				$schemaFilter = '';
				if ( $schemaNames !== [] ) {
					$schemaFilter = 'AND any(label IN labels(n) WHERE label IN $schemaNames)';
				}

				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					"MATCH (n:Subject)
					 WHERE toLower(n.name) STARTS WITH toLower(\$search)
					 $schemaFilter
					 RETURN n.id AS id, n.name AS name
					 ORDER BY n.name
					 LIMIT \$limit",
					[
						'search' => $search,
						'limit' => (int)$limit,
						'schemaNames' => $schemaNames
					]
				);

				$subjects = [];
				foreach ( $result as $row ) {
					$subjects[] = new SubjectLabelLookupResult(
						id: $row->get( 'id' ),
						label: $row->get( 'name' )
					);
				}

				return $subjects;
			}
		);
	}

}
