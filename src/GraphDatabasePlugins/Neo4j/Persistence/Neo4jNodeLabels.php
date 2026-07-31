<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;

/**
 * Adds and removes labels on Subject nodes identified by their id, within a given transaction.
 *
 * Shared by Neo4jSubjectUpdater (reconciling subjects with their schemas) and Neo4jProjectionStore
 * (stripping subjects down to stubs). Those classes hold their transaction differently, so the
 * transaction is passed in per call rather than owned here.
 *
 * Only Subject nodes are addressed: passing the id of any other kind of node matches nothing.
 */
class Neo4jNodeLabels {

	/**
	 * @param array<string, string[]> $labelsBySubjectId
	 */
	public static function add( TransactionInterface $transaction, array $labelsBySubjectId ): void {
		self::applyLabels( $transaction, 'SET', $labelsBySubjectId );
	}

	/**
	 * @param array<string, string[]> $labelsBySubjectId
	 */
	public static function remove( TransactionInterface $transaction, array $labelsBySubjectId ): void {
		self::applyLabels( $transaction, 'REMOVE', $labelsBySubjectId );
	}

	/**
	 * @param array<string, string[]> $labelsBySubjectId
	 */
	private static function applyLabels(
		TransactionInterface $transaction,
		string $cypherClause,
		array $labelsBySubjectId
	): void {
		foreach ( Neo4jLabelGroups::build( $labelsBySubjectId ) as $group ) {
			$transaction->run(
				'UNWIND $subjectIds AS subjectId
					MATCH (node:Subject {id: subjectId})
					' . $cypherClause . ' node:' . Cypher::buildLabelList( $group['labels'] ),
				[ 'subjectIds' => $group['subjectIds'] ]
			);
		}
	}

}
