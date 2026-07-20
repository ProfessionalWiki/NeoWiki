<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;

/**
 * Reads and removes the labels of a Neo4j node identified by its id, within a given transaction.
 *
 * Shared by Neo4jSubjectUpdater (reconciling a subject's labels with its schema) and
 * Neo4jProjectionStore (stripping a subject down to a stub). Those classes hold their transaction
 * differently, so the transaction is passed in per call rather than owned here.
 */
class Neo4jNodeLabels {

	/**
	 * @return string[]
	 */
	public static function read( TransactionInterface $transaction, string $nodeId ): array {
		/**
		 * @var SummarizedResult $result
		 */
		$result = $transaction->run(
			'MATCH (n {id: $id}) RETURN labels(n) AS labels',
			[ 'id' => $nodeId ]
		);

		if ( $result->isEmpty() ) {
			return [];
		}

		return $result->first()->get( 'labels' )->toArray();
	}

	/**
	 * @param string[] $labels
	 */
	public static function remove( TransactionInterface $transaction, string $nodeId, array $labels ): void {
		if ( $labels === [] ) {
			return;
		}

		$transaction->run(
			'MATCH (n {id: $id}) REMOVE n:' . Cypher::buildLabelList( $labels ),
			[ 'id' => $nodeId ]
		);
	}

}
