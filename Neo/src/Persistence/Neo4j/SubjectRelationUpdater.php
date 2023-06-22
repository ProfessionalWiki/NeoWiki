<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SubjectRelationUpdater {

	public function __construct(
		private readonly SubjectId $subjectId,
		private readonly RelationList $relations,
		private readonly TransactionInterface $transaction,
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
		$this->transaction->run(
			'
				MATCH ({id: $subjectId})-[relation]->()
				WHERE NOT relation.id IN $relationIds
				DELETE relation',
			[
				'subjectId' => $this->subjectId->text,
				'relationIds' => $relationIds,
			]
		);
	}

	private function removeIfTypeOrTargetChanged( Relation $relation ): void {
		$this->transaction->run(
			'MATCH (subject {id: $subjectId})-[oldRelation {id: $relationId}]->()
			 WHERE oldRelation.type <> $relationType OR NOT (subject)-[oldRelation]->({id: $targetId})
			 DELETE oldRelation',
			[
				'subjectId' => $this->subjectId->text,
				'relationId' => $relation->id->asString(),
				'relationType' => $relation->type->text,
				'targetId' => $relation->targetId->text,
			]
		);
	}

	private function createOrUpdate( Relation $relation ): void {
		$this->transaction->run(
			'MERGE (subject {id: $subjectId})
			 MERGE (target {id: $targetId})
			 MERGE (subject)-[relation:' . Cypher::escape( $relation->type->text ) . ' {id: $relationId}]->(target)
			 SET relation = $relationProperties',
			[
				'subjectId' => $this->subjectId->text,
				'relationId' => $relation->id->asString(),
				'relationProperties' => $this->getPropertiesForNeo4j( $relation ),
				'targetId' => $relation->targetId->text,
			]
		);
	}

	private function getPropertiesForNeo4j( Relation $relation ): array {
		return array_merge(
			$relation->properties->map,
			[ 'id' => $relation->id->asString() ]
		);
	}

}
