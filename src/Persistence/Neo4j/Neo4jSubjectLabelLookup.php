<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectSearchResult;

class Neo4jSubjectLabelLookup implements SubjectLabelLookup {

	private const LIMIT = 10;

	public function __construct(
		private readonly ClientInterface $client
	) {
	}

	/**
	 * @return SubjectSearchResult[]
	 */
	public function getSubjectLabelsMatching( string $search ): array {
		if ( trim( $search ) === '' ) {
			return [];
		}

		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $search ): array {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					'MATCH (n:Subject)
					 WHERE toLower(n.name) STARTS WITH toLower($search)
					 RETURN n.id AS id, n.name AS name
					 LIMIT $limit',
					[
						'search' => $search,
						'limit' => self::LIMIT
					]
				);

				$subjects = [];
				foreach ( $result as $row ) {
					$subjects[] = new SubjectSearchResult(
						id: $row->get( 'id' ),
						label: $row->get( 'name' )
					);
				}

				return $subjects;
			}
		);
	}

}
