<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelation;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class Neo4jSubjectRelationUpdater {

	public function __construct(
		private readonly SubjectId $subjectId,
		private readonly TypedRelationList $relations,
		private readonly TransactionInterface $transaction,
		private readonly string $wikiId,
		private readonly Neo4jOrphanCandidates $orphanCandidates,
	) {
	}

	public function updateRelations(): void {
		$relationIds = $this->relations->getIdsAsStringArray();

		$this->removeNonexistentRelations( $relationIds );

		foreach ( $this->relations->relations as $relation ) {
			$this->removeIfTypeOrTargetChanged( $relation );
			$this->createOrUpdate( $relation );
		}
	}

	private function removeNonexistentRelations( array $relationIds ): void {
		$this->collectOrphanCandidates( $this->transaction->run(
			'
				MATCH ({id: $subjectId})-[relation]->(target)
				WHERE NOT relation.id IN $relationIds
				DELETE relation
				RETURN DISTINCT target.id AS id',
			[
				'subjectId' => $this->subjectId->text,
				'relationIds' => $relationIds,
			]
		) );
	}

	private function removeIfTypeOrTargetChanged( TypedRelation $relation ): void {
		$this->collectOrphanCandidates( $this->transaction->run(
			'MATCH (subject {id: $subjectId})-[oldRelation {id: $relationId}]->(oldTarget)
			 WHERE oldRelation.type <> $relationType OR NOT (subject)-[oldRelation]->({id: $targetId})
			 DELETE oldRelation
			 RETURN DISTINCT oldTarget.id AS id',
			[
				'subjectId' => $this->subjectId->text,
				'relationId' => $relation->id->asString(),
				'relationType' => $relation->type->text,
				'targetId' => $relation->targetId->text,
			]
		) );
	}

	/**
	 * Records the targets of the relations just deleted: losing an incoming relation is what can leave
	 * a target as an orphan stub, swept at the end of the transaction.
	 */
	private function collectOrphanCandidates( SummarizedResult $deletedRelationTargets ): void {
		$this->orphanCandidates->add( ...array_map(
			fn ( $record ) => $record->get( 'id' ),
			$deletedRelationTargets->toArray()
		) );
	}

	private function createOrUpdate( TypedRelation $relation ): void {
		// A relation whose target Subject does not exist yet creates it as a stub: a node with only
		// the id and wiki_id properties and the Subject label. ON CREATE keeps an already-existing
		// target (a real Subject or an earlier stub) untouched. The stub is upgraded in place when the
		// real Subject is later saved, since the save path also matches the node by id alone.
		$this->transaction->run(
			'MERGE (subject {id: $subjectId})
			 MERGE (target {id: $targetId})
			 ON CREATE SET target:Subject, target.wiki_id = $wikiId
			 MERGE (subject)-[relation:' . Cypher::escape( $relation->type->text ) . ' {id: $relationId}]->(target)
			 SET relation = $relationProperties',
			[
				'subjectId' => $this->subjectId->text,
				'relationId' => $relation->id->asString(),
				'relationProperties' => $this->getPropertiesForNeo4j( $relation ),
				'targetId' => $relation->targetId->text,
				'wikiId' => $this->wikiId,
			]
		);
	}

	private function getPropertiesForNeo4j( TypedRelation $relation ): array {
		return array_merge(
			$relation->properties->map,
			[ 'id' => $relation->id->asString() ]
		);
	}

}
