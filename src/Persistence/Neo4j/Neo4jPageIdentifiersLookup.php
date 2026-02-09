<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class Neo4jPageIdentifiersLookup implements PageIdentifiersLookup {

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function getPageIdOfSubject( SubjectId $subjectId ): ?PageIdentifiers {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $subjectId ): ?PageIdentifiers {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					'
					MATCH (page:Page)-[:HasSubject]->(subject {id: $subjectId})
					RETURN page.id AS id, page.name as name',
					[ 'subjectId' => $subjectId->text ]
				);

				$arrayResult = $result->getResults()->toRecursiveArray();

				if ( array_key_exists( 0, $arrayResult ) && is_array( $arrayResult[0] ) ) {
					$page = $arrayResult[0];

					if ( array_key_exists( 'id', $page ) && array_key_exists( 'name', $page ) ) {
						return new PageIdentifiers(
							id: new PageId( (int)$page['id'] ),
							title: $page['name']
						);
					}
				}

				return null;
			}
		);
	}

}
