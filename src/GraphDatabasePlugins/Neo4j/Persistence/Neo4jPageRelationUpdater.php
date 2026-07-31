<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelation;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;

/**
 * Reconciles the outgoing relations of a set of Subjects with the relations those Subjects hold.
 *
 * One query per concern rather than per relation: dropping relations that are gone, dropping those
 * whose type or target changed, and upserting the rest. Cypher cannot parameterize relationship
 * types, so the upserts are grouped by type, one query per distinct type.
 */
class Neo4jPageRelationUpdater {

	public function __construct(
		private readonly TransactionInterface $transaction,
		private readonly string $wikiId,
		private readonly Neo4jOrphanCandidates $orphanCandidates,
	) {
	}

	/**
	 * @param array<string, TypedRelationList> $relationsBySubjectId
	 */
	public function updateRelations( array $relationsBySubjectId ): void {
		if ( $relationsBySubjectId === [] ) {
			return;
		}

		$this->removeNonexistentRelations( $relationsBySubjectId );
		$this->removeRelationsWithChangedTypeOrTarget( $relationsBySubjectId );
		$this->createOrUpdateRelations( $relationsBySubjectId );
	}

	/**
	 * @param array<string, TypedRelationList> $relationsBySubjectId
	 */
	private function removeNonexistentRelations( array $relationsBySubjectId ): void {
		$subjects = [];

		foreach ( $relationsBySubjectId as $subjectId => $relations ) {
			$subjects[] = [
				'subjectId' => (string)$subjectId,
				'relationIds' => $relations->getIdsAsStringArray(),
			];
		}

		$this->collectOrphanCandidates( $this->transaction->run(
			'UNWIND $subjects AS subject
				MATCH (:Subject {id: subject.subjectId})-[relation]->(target)
				WHERE NOT relation.id IN subject.relationIds
				DELETE relation
				RETURN DISTINCT target.id AS id',
			[ 'subjects' => $subjects ]
		) );
	}

	/**
	 * @param array<string, TypedRelationList> $relationsBySubjectId
	 */
	private function removeRelationsWithChangedTypeOrTarget( array $relationsBySubjectId ): void {
		$rows = [];

		foreach ( $this->eachRelation( $relationsBySubjectId ) as [ $subjectId, $relation ] ) {
			$rows[] = [
				'subjectId' => $subjectId,
				'relationId' => $relation->id->asString(),
				'relationType' => $relation->type->text,
				'targetId' => $relation->targetId->text,
			];
		}

		if ( $rows === [] ) {
			return;
		}

		$this->collectOrphanCandidates( $this->transaction->run(
			'UNWIND $relations AS row
				MATCH (subject:Subject {id: row.subjectId})-[oldRelation {id: row.relationId}]->(oldTarget)
				WHERE oldRelation.type <> row.relationType
					OR NOT (subject)-[oldRelation]->(:Subject {id: row.targetId})
				DELETE oldRelation
				RETURN DISTINCT oldTarget.id AS id',
			[ 'relations' => $rows ]
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

	/**
	 * @param array<string, TypedRelationList> $relationsBySubjectId
	 */
	private function createOrUpdateRelations( array $relationsBySubjectId ): void {
		$rowsByType = [];

		foreach ( $this->eachRelation( $relationsBySubjectId ) as [ $subjectId, $relation ] ) {
			$rowsByType[$relation->type->text][] = [
				'subjectId' => $subjectId,
				'relationId' => $relation->id->asString(),
				'targetId' => $relation->targetId->text,
				'properties' => $this->getPropertiesForNeo4j( $relation ),
			];
		}

		foreach ( $rowsByType as $relationType => $rows ) {
			// A relation whose target Subject does not exist yet creates it as a stub: a node with only
			// the id and wiki_id properties and the Subject label. ON CREATE keeps an already-existing
			// target (a real Subject or an earlier stub) untouched. The stub is upgraded in place when the
			// real Subject is later saved, since the save path matches the same :Subject label and id.
			$this->transaction->run(
				'UNWIND $relations AS row
					MERGE (subject:Subject {id: row.subjectId})
					MERGE (target:Subject {id: row.targetId})
					ON CREATE SET target.wiki_id = $wikiId
					MERGE (subject)-[relation:' . Cypher::escape( (string)$relationType ) . ' {id: row.relationId}]->(target)
					SET relation = row.properties',
				[
					'relations' => $rows,
					'wikiId' => $this->wikiId,
				]
			);
		}
	}

	private function getPropertiesForNeo4j( TypedRelation $relation ): array {
		return array_merge(
			$relation->properties->map,
			[ 'id' => $relation->id->asString() ]
		);
	}

	/**
	 * @param array<string, TypedRelationList> $relationsBySubjectId
	 * @return iterable<array{string, TypedRelation}>
	 */
	private function eachRelation( array $relationsBySubjectId ): iterable {
		foreach ( $relationsBySubjectId as $subjectId => $relations ) {
			foreach ( $relations->relations as $relation ) {
				yield [ (string)$subjectId, $relation ];
			}
		}
	}

}
