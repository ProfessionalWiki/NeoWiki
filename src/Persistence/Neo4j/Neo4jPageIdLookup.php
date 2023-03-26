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
					'MATCH (page:Page)-[:HasSubject]->({id: $subjectId}) RETURN page.id',
					[ 'subjectId' => $subjectId->text ]
				);

				// TODO

				return null;
			}
		);
	}

}
