<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\PageIdLookup;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;

class Neo4jPageIdLookup implements PageIdLookup {

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function getPageIdOfSubject( SubjectId $subjectId ): ?int {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $subjectId ): ?int {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					'
					MATCH (page:Page)-[:HasSubject]->(subject)
					WHERE subject.id = $subjectId
					RETURN page.id AS pageId',
					[ 'subjectId' => $subjectId->text ]
				);

				$arrayResult = $result->getResults()->toRecursiveArray();

				if ( array_key_exists( 0, $arrayResult ) && array_key_exists( 'pageId', $arrayResult[0] ) ) {
					return (int)$arrayResult[0]['pageId'];
				}

				return null;
			}
		);
	}

}
