<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Psr\Log\LoggerInterface;

class Neo4jSubjectUpdater {

	public function __construct(
		private readonly TransactionInterface $transaction,
		private readonly PageId $pageId,
		private readonly SchemaLookup $schemaRepository,
		private readonly Neo4jValueBuilderRegistry $valueBuilderRegistry,
		private readonly LoggerInterface $logger,
		private readonly string $wikiId,
	) {
	}

	public function updateSubject( Subject $subject, bool $isMainSubject ): void {
		// TODO: we should make sure this schema retrieval is cached
		$schema = $this->schemaRepository->getSchema( $subject->getSchemaName() );

		if ( $schema === null ) {
			$this->logger->warning( 'Schema not found: ' . $subject->getSchemaName()->getText() );
			return;
		}

		// Note: the below method calls might need to be in this order
		$this->updateNodeProperties( $subject );
		$this->updateRelations( $subject, $schema );
		$this->updateHasSubjectRelation( $subject, $isMainSubject );
		$this->updateNodeLabels( $subject );
	}

	private function updateNodeProperties( Subject $subject ): void {
		$this->transaction->run(
			'MERGE (n {id: $id}) SET n = $props',
			[
				'id' => $subject->id->text,
				'props' => array_merge(
					$this->statementsToNodeProperties( $subject->getStatements() ),
					[
						'name' => $subject->label->text,
						'id' => $subject->id->text,
						'wiki_id' => $this->wikiId,
					]
				),
			]
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function statementsToNodeProperties( StatementList $statements ): array {
		$nodeProps = [];

		foreach ( $statements->asArray() as $statement ) {
			$propertyTypeName = $statement->getPropertyType();

			if ( !$this->valueBuilderRegistry->hasBuilder( $propertyTypeName ) ) {
				continue;
			}

			$neo4jValue = $this->valueBuilderRegistry->buildNeo4jValue( $propertyTypeName, $statement->getValue() );

			if ( $neo4jValue !== null ) {
				$this->warnOnDroppedValues( $statement, $neo4jValue );
				$nodeProps[$statement->getPropertyName()->text] = $neo4jValue;
			}
		}

		return $nodeProps;
	}

	/**
	 * A value builder can drop values it cannot represent in the graph (e.g. an
	 * unparseable dateTime), leaving the projection with fewer values than the
	 * authoritative revision slot. That divergence is otherwise silent, so warn.
	 */
	private function warnOnDroppedValues( Statement $statement, mixed $neo4jValue ): void {
		$inputValues = $statement->getValue()->toScalars();

		if ( !is_array( $inputValues ) || !is_array( $neo4jValue ) ) {
			return;
		}

		$droppedCount = count( $inputValues ) - count( $neo4jValue );

		if ( $droppedCount < 1 ) {
			return;
		}

		$this->logger->warning(
			'Dropped ' . $droppedCount . ' unpersistable value(s) of property "'
			. $statement->getPropertyName()->text . '" on page ' . $this->pageId->id
			. ' when projecting to the graph'
		);
	}

	private function updateHasSubjectRelation( Subject $subject, bool $isMainSubject ): void {
		$this->transaction->run(
			'MATCH (page:Page {id: $pageId, wiki_id: $wikiId}), (subject {id: $subjectId})
					MERGE (page)-[:HasSubject {isMain: $isMainSubject}]->(subject)',
			[
				'pageId' => $this->pageId->id,
				'wikiId' => $this->wikiId,
				'subjectId' => $subject->id->text,
				'isMainSubject' => $isMainSubject,
			]
		);
	}

	private function updateNodeLabels( Subject $subject ): void {
		$oldLabels = $this->getNodeLabels( $subject->id );
		$newLabels = [ 'Subject', $subject->getSchemaName()->getText() ];

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
		$updater = new Neo4jSubjectRelationUpdater(
			$subject->getId(),
			$subject->getTypedRelations( $schema ),
			$this->transaction
		);
		$updater->updateRelations();
	}

}
