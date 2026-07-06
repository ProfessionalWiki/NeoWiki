<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;

readonly class Neo4jClientWriteQueryEngine implements Neo4jWriteQueryEngine {

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function runWriteQuery( string $cypher ): SummarizedResult {
		return $this->client->writeTransaction(
			static function ( TransactionInterface $transaction ) use ( $cypher ): SummarizedResult {
				return $transaction->run( $cypher );
			}
		);
	}

}
