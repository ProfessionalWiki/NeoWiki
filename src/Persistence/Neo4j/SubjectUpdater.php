<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SubjectUpdater {

	public function __construct(
		private readonly TransactionInterface $transaction,
		private readonly PageId $pageId
	) {
	}

	public function updateSubject( Subject $subject ): void {
		$this->updateNodeProperties( $subject );
		$this->updateRelations( $subject );
		$this->updateHasSubjectRelation( $subject );
		$this->updateNodeLabels( $subject );
	}

	private function updateHasSubjectRelation( Subject $subject ): void {
		$this->transaction->run(
			'MATCH (page:Page {id: $pageId}), (subject {id: $subjectId})
					MERGE (page)-[:HasSubject]->(subject)',
			[
				'pageId' => $this->pageId->id,
				'subjectId' => $subject->id->text,
			]
		);
	}

	private function updateNodeProperties( Subject $subject ): void {
		$this->transaction->run(
			'MERGE (n {id: $id}) SET n = $props',
			[
				'id' => $subject->id->text,
				'props' => array_merge(
					$subject->getProperties()->map,
					[
						'name' => $subject->label->text,
						'id' => $subject->id->text,
					]
				),
			]
		);
	}

	private function updateNodeLabels( Subject $subject ): void {
		$oldLabels = $this->getNodeLabels( $subject->id );
		$newLabels = $subject->types->toStringArray();

		$labelsToRemove = array_diff( $oldLabels, $newLabels );

		if ( $labelsToRemove !== [] ) {
			$this->transaction->run(
				'MATCH (n {id: $id}) REMOVE n:' . Cypher::buildLabelList( $labelsToRemove ),
				[ 'id' => $subject->id->text ]
			);
		}

		$labelsToAdd = array_diff( $newLabels, $oldLabels );

		if ( $labelsToAdd !== [] ) {
			$this->transaction->run(
				'MATCH (n {id: $id}) SET n:' . Cypher::buildLabelList( $labelsToAdd ),
				[ 'id' => $subject->id->text ]
			);
		}
	}

	/**
	 * @return string[]
	 */
	private function getNodeLabels( SubjectId $id ): array {
		/**
		 * @var SummarizedResult $result
		 */
		$result = $this->transaction->run(
			'MATCH (n) WHERE n.id = $id RETURN labels(n)',
			[ 'id' => $id->text ]
		);

		if ( $result->isEmpty() ) {
			return [];
		}

		/**
		 * @var CypherList $labels
		 */
		$labels = $result->first()->get( 'labels(n)' );

		return $labels->toArray();
	}

	private function updateRelations( Subject $subject ): void {
		// Delete relations that are no longer present
		$this->transaction->run(
			'
				MATCH (subject {id: $subjectId})-[relation]->()
				WHERE NOT relation.id IN $relationIds
				DELETE relation',
			[
				'subjectId' => $subject->id->text,
				'relationIds' => $subject->getRelationsAsIdStringArray()
			]
		);

		// Create or update relations
		foreach ( $subject->relations->relations as $relation ) {
			$this->transaction->run(
				'
					MATCH (subject {id: $subjectId})
					MERGE (target {id: $targetId})
					MERGE (subject)-[relation:' . Cypher::escape( $relation->type->text ) . ']->(target)
						ON CREATE SET relation=$relationProperties ON MATCH SET relation=$relationProperties',
				[
					'subjectId' => $subject->id->text,
					'targetId' => $relation->targetId->text,
					'relationProperties' => array_merge( $relation->properties->map, [ 'id' => $relation->id->text ] ),
				]
			);
		}
	}

}
