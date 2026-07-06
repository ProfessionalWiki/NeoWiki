<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;

readonly class Neo4jClientReadQueryEngine implements Neo4jReadQueryEngine {

	public function __construct(
		private ClientInterface $readOnlyClient,
	) {
	}

	public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
		$transactionConfig = $timeoutSeconds === null
			? null
			: TransactionConfiguration::default()->withTimeout( (float)$timeoutSeconds );

		return $this->readOnlyClient->readTransaction(
			static function ( TransactionInterface $transaction ) use ( $cypher, $parameters ): SummarizedResult {
				return $transaction->run( $cypher, $parameters );
			},
			null,
			$transactionConfig
		);
	}

}
