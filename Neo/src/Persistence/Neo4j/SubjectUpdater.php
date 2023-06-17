<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class SubjectUpdater {

	public function __construct(
		private readonly SchemaLookup $schemaRepository,
		private readonly TransactionInterface $transaction,
		private readonly PageId $pageId
	) {
	}

	public function updateSubject( Subject $subject, bool $isMainSubject ): void {
		$schema = $this->schemaRepository->getSchema( $subject->getSchemaId() );

		if ( $schema === null ) {
			// TODO: log warning
			return;
		}

		// Note: the below method calls might need to be in this order
		$this->updateNodeProperties( $subject, $schema );
		$this->updateRelations( $subject, $schema );
		$this->updateHasSubjectRelation( $subject, $isMainSubject );
		$this->updateNodeLabels( $subject );
	}

	private function updateNodeProperties( Subject $subject, Schema $schema ): void {
		$this->transaction->run(
			'MERGE (n {id: $id}) SET n = $props',
			[
				'id' => $subject->id->text,
				'props' => array_merge(
					// TODO: this explodes if an object such as a relation is in the map
					// Add some safety code, especially if we continue to not have a more solid model for values
					$subject->getStatements()->withoutRelations( $schema )->asMap(),
					[
						'name' => $subject->label->text,
						'id' => $subject->id->text,
					]
				),
			]
		);
	}

	private function updateHasSubjectRelation( Subject $subject, bool $isMainSubject ): void {
		$this->transaction->run(
			'MATCH (page:Page {id: $pageId}), (subject {id: $subjectId})
					MERGE (page)-[:HasSubject {isMain: $isMainSubject}]->(subject)',
			[
				'pageId' => $this->pageId->id,
				'subjectId' => $subject->id->text,
				'isMainSubject' => $isMainSubject,
			]
		);
	}

	private function updateNodeLabels( Subject $subject ): void {
		$oldLabels = $this->getNodeLabels( $subject->id );
		$newLabels = [ $subject->getSchemaId()->getText() ];

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

	private function updateRelations( Subject $subject, Schema $schema ): void {
		$updater = new SubjectRelationUpdater(
			$subject->getId(),
			$subject->getRelations( $schema ),
			$this->transaction
		);
		$updater->updateRelations();
	}

}
