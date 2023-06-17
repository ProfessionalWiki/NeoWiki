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

		foreach ( $this->relations->relations as $relation ) {
			$this->transaction->run(
				'
					MATCH (subject {id: $subjectId})
					OPTIONAL MATCH (subject)-[oldRelation {id: $relationId}]->(oldTarget)
					WITH subject, oldRelation, oldTarget,
					     oldRelation IS NOT NULL AND (oldRelation.type <> $relationType OR oldTarget.id <> $targetId) AS shouldReplace,
					     oldRelation IS NULL AS shouldCreate
					WHERE shouldReplace OR shouldCreate
					FOREACH (_ IN CASE WHEN shouldReplace THEN [1] ELSE [] END |
						DELETE oldRelation
					)
					FOREACH (_ IN CASE WHEN shouldReplace OR shouldCreate THEN [1] ELSE [] END |
						MERGE (target {id: $targetId})
						CREATE (subject)-[newRelation:' . Cypher::escape( $relation->type->text ) . ']->(target)
						SET newRelation = $relationProperties
					)
				',
				[
					'subjectId' => $this->subjectId->text,
					'relationId' => $relation->id->asString(),
					'relationType' => $relation->type->text,
					'targetId' => $relation->targetId->text,
					'relationProperties' => $this->getPropertiesForNeo4j( $relation ),
				]
			);
		}
	}

	private function getPropertiesForNeo4j( Relation $relation ): array {
		return array_merge(
			$relation->properties->map,
			[ 'id' => $relation->id->asString() ]
		);
	}

}
