<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Plan;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;

readonly class ExplainCypherQueryValidator implements CypherQueryValidator {

	/**
	 * Read-only plan operators known to Neo4j 5.x. Any operator not in this list
	 * causes the query to be classified as non-read-only.
	 */
	private const array ALLOWED_OPERATORS = [
		// Result production
		'ProduceResults',
		'EmptyResult',

		// Node scans and seeks
		'AllNodesScan',
		'NodeByLabelScan',
		'NodeByIdSeek',
		'NodeByElementIdSeek',
		'NodeUniqueIndexSeek',
		'NodeIndexSeek',
		'NodeIndexScan',
		'NodeIndexContainsScan',
		'NodeIndexEndsWithScan',
		'IntersectionNodeByLabelsScan',
		'UnionNodeByLabelsScan',
		'MultiNodeIndexSeek',

		// Relationship scans and seeks
		'DirectedRelationshipTypeScan',
		'UndirectedRelationshipTypeScan',
		'DirectedRelationshipByIdSeek',
		'UndirectedRelationshipByIdSeek',
		'DirectedRelationshipByElementIdSeek',
		'UndirectedRelationshipByElementIdSeek',
		'DirectedAllRelationshipsScan',
		'UndirectedAllRelationshipsScan',
		'DirectedRelationshipIndexSeek',
		'UndirectedRelationshipIndexSeek',
		'DirectedRelationshipIndexScan',
		'UndirectedRelationshipIndexScan',
		'DirectedRelationshipIndexContainsScan',
		'UndirectedRelationshipIndexContainsScan',
		'DirectedRelationshipIndexEndsWithScan',
		'UndirectedRelationshipIndexEndsWithScan',
		'DirectedUnionRelationshipTypesScan',
		'UndirectedUnionRelationshipTypesScan',

		// Expand (path traversal)
		'Expand(All)',
		'Expand(Into)',
		'OptionalExpand(All)',
		'OptionalExpand(Into)',
		'VarLengthExpand(All)',
		'VarLengthExpand(Into)',
		'VarLengthExpand(Pruning)',
		'BFSPruningVarExpand',

		// Shortest path
		'ShortestPath',
		'AllShortestPaths',
		'StatefulShortestPath',

		// Filter and transform
		'Filter',
		'Projection',
		'Limit',
		'ExhaustiveLimit',
		'Skip',
		'Sort',
		'PartialSort',
		'Top',
		'PartialTop',
		'Distinct',
		'OrderedDistinct',
		'Aggregation',
		'OrderedAggregation',
		'EagerAggregation',
		'Eager',
		'CacheProperties',
		'UnwindCollection',
		'Unwind',
		'NodeCountFromCountStore',
		'RelationshipCountFromCountStore',

		// Joins
		'CartesianProduct',
		'NodeHashJoin',
		'ValueHashJoin',
		'NodeLeftOuterHashJoin',
		'NodeRightOuterHashJoin',
		'AssertSameNode',
		'AssertSameRelationship',

		// Apply variants
		'Apply',
		'SemiApply',
		'AntiSemiApply',
		'SelectOrSemiApply',
		'SelectOrAntiSemiApply',
		'LetSemiApply',
		'LetAntiSemiApply',
		'ConditionalApply',
		'AntiConditionalApply',
		'RollUpApply',

		// Set operations
		'Union',
		'OrderedUnion',

		// Other
		'Argument',
		'Input',
		'Optional',

		// Parallel variants
		'PartitionedAllNodesScan',
		'PartitionedNodeByLabelScan',
		'PartitionedDirectedRelationshipTypeScan',
		'PartitionedUndirectedRelationshipTypeScan',
		'PartitionedDirectedAllRelationshipsScan',
		'PartitionedUndirectedAllRelationshipsScan',
		'PartitionedNodeIndexScan',
		'PartitionedNodeIndexSeek',
		'PartitionedDirectedRelationshipIndexScan',
		'PartitionedUndirectedRelationshipIndexScan',
		'PartitionedDirectedRelationshipIndexSeek',
		'PartitionedUndirectedRelationshipIndexSeek',
		'PartitionedIntersectionNodeByLabelsScan',
		'PartitionedUnionNodeByLabelsScan',
		'PartitionedDirectedUnionRelationshipTypesScan',
		'PartitionedUndirectedUnionRelationshipTypesScan',
	];

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function queryIsAllowed( string $cypher ): bool {
		$plan = $this->getExplainPlan( $cypher );

		return $this->allOperatorsAllowed( $plan );
	}

	private function getExplainPlan( string $cypher ): Plan {
		$result = $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $cypher ) {
				return $transaction->run( 'EXPLAIN ' . $cypher );
			}
		);

		return $result->getSummary()->getPlan()
			?? throw new \RuntimeException( 'EXPLAIN did not return a plan' );
	}

	private function allOperatorsAllowed( Plan $plan ): bool {
		if ( !$this->isAllowedOperator( $plan->getOperator() ) ) {
			return false;
		}

		foreach ( $plan->getChildren() as $child ) {
			if ( !$this->allOperatorsAllowed( $child ) ) {
				return false;
			}
		}

		return true;
	}

	private function isAllowedOperator( string $operator ): bool {
		return in_array( $this->stripDatabaseSuffix( $operator ), self::ALLOWED_OPERATORS );
	}

	private function stripDatabaseSuffix( string $operator ): string {
		$atPosition = strpos( $operator, '@' );

		return $atPosition !== false ? substr( $operator, 0, $atPosition ) : $operator;
	}

}
