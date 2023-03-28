<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\PageInfo;
use ProfessionalWiki\NeoWiki\Domain\Subject;
use ProfessionalWiki\NeoWiki\Domain\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\Cypher;
use RuntimeException;

class Neo4jQueryStore implements QueryStore {

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function savePage( int $pageId, PageInfo $pageInfo, SubjectMap $subjects ): void {
		$this->client->writeTransaction( function ( TransactionInterface $transaction ) use ( $pageId, $pageInfo, $subjects ) {
			$transaction->run(
				'
				// Create or update the page
				MERGE (page:Page {id: $pageId})
				SET page.id = $pageId, page.name = $pageTitle, page.lastUpdated = datetime()

				// Delete subjects that are no longer present on the page
				WITH page
				MATCH (page)-[r:HasSubject]->(subject)
				WHERE NOT subject.id IN $subjectIds
				DELETE r, subject

				// Remove all existing HasSubject relations
				WITH page
				MATCH (page)-[r:HasSubject]->()
				DELETE r
				',
				[
					'pageId' => $pageId,
					'pageTitle' => $pageInfo->title,
					'subjectIds' => $subjects->getIdsAsTextArray()
				]
			);

			foreach ( $subjects->asArray() as $subject ) {
				$this->updateNodeProperties( $transaction, $subject );
				$this->updateRelations( $transaction, $subject );
				$this->updateHasSubjectRelation( $transaction, $subject, $pageId );
				$this->updateNodeLabels( $transaction, $subject );
			}
		} );
	}

	private function updateHasSubjectRelation( TransactionInterface $transaction, Subject $subject, int $pageId ): void {
		$transaction->run(
			'MATCH (page:Page {id: $pageId}), (subject {id: $subjectId})
					MERGE (page)-[:HasSubject]->(subject)',
			[
				'pageId' => $pageId,
				'subjectId' => $subject->id->text,
			]
		);
	}

	private function updateNodeProperties( TransactionInterface $transaction, Subject $subject ): void {
		$transaction->run(
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

	private function updateNodeLabels( TransactionInterface $transaction, Subject $subject ): void {
		$oldLabels = $this->getNodeLabels( $transaction, $subject->id );
		$newLabels = $subject->types->toStringArray();

		$labelsToRemove = array_diff( $oldLabels, $newLabels );

		if ( $labelsToRemove !== [] ) {
			$transaction->run(
				'MATCH (n {id: $id}) REMOVE n:' . Cypher::buildLabelList( $labelsToRemove ),
				[ 'id' => $subject->id->text ]
			);
		}

		$labelsToAdd = array_diff( $newLabels, $oldLabels );

		if ( $labelsToAdd !== [] ) {
			$transaction->run(
				'MATCH (n {id: $id}) SET n:' . Cypher::buildLabelList( $labelsToAdd ),
				[ 'id' => $subject->id->text ]
			);
		}
	}

	/**
	 * @return string[]
	 */
	private function getNodeLabels( TransactionInterface $transaction, SubjectId $id ): array {
		/**
		 * @var SummarizedResult $result
		 */
		$result = $transaction->run(
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

	private function updateRelations( TransactionInterface $transaction, Subject $subject ): void {
		// Delete relations that are no longer present
		$transaction->run(
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
			$transaction->run(
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

	// TODO: add deleteSubject function
	// TODO: page should not always be deleted due to incoming links. Difference between ID and title in meaning

	public function deletePage( int $pageId ): void {
		$this->client->writeTransaction( function ( TransactionInterface $transaction ) use ( $pageId ) {
			foreach ( $this->getSubjectIdsByPageId( $transaction, $pageId ) as $subjectId ) {
				$this->deleteSubject( $transaction, new SubjectId( $subjectId ) );
			}
		} );
	}

	/**
	 * @return string[]
	 */
	private function getSubjectIdsByPageId( TransactionInterface $transaction, int $pageId ): array {
		/**
		 * @var SummarizedResult $result
		 */
		$result = $transaction->run(
			'MATCH (page:Page {id: $pageId})-[:HasSubject]->(subject:Subject)
				RETURN subject.id AS id, subject AS properties, labels(subject) AS labels',
			[ 'pageId' => $pageId ]
		);

		return $result->toArray();
	}

	private function deleteSubject( TransactionInterface $transaction, SubjectId $subjectId ): void {
		if ( $this->subjectHasIncomingRelations( $transaction, $subjectId ) ) {
			$transaction->run(
				'
					MATCH ()-[hsRelation:HasSubject]->(subject {id: $subjectId})
					OPTIONAL MATCH (subject)-[outgoingSubjectRelation]->(o)
					DELETE hsRelation, outgoingSubjectRelation
					',
				[ 'subjectId' => $subjectId->text ]
			);
			// TODO: clear properties?
			// TODO: clear labels?
		}
		else {
			$transaction->run(
				'MATCH (subject {id: $subjectId})
				DETACH DELETE subject',
				[ 'subjectId' => $subjectId->text ]
			);
		}
	}

	private function subjectHasIncomingRelations( TransactionInterface $transaction, SubjectId $subjectId ): bool {
		return $transaction->run(
			'MATCH (subject {id: $subjectId})<-[incomingRelation]-()
			WHERE NOT incomingRelation:HasSubject
			RETURN incomingRelation',
			[ 'subjectId' => $subjectId->text ]
		)->isEmpty();
	}

	public function runReadQuery( string $cypher ): SummarizedResult {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $cypher ): SummarizedResult {
				return $transaction->run( $cypher );
			}
		);
	}

	public function getPageIdForSubject( SubjectId $subjectId ): ?int {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $subjectId ): ?int {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					'MATCH (page:Page)-[:HasSubject]->(subject {id: $subjectId})
					RETURN page.id AS id',
					[ 'subjectId' => $subjectId->text ]
				);

				if ( $result->isEmpty() ) {
					return null;
				}

				return (int)$result->first()->get( 'id' );
			}
		);
	}

}
