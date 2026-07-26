<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
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

		// updateNodeProperties must stay first: it is what brings the node into existence, and the
		// steps after it match that node as a :Subject. Reordering it after any of them would have
		// them create a second, label-less node for the same id.
		$this->updateNodeProperties( $subject );
		$this->updateRelations( $subject, $schema );
		$this->updateHasSubjectRelation( $subject, $isMainSubject );
		$this->updateNodeLabels( $subject );
	}

	/**
	 * Creates the node with the :Subject label rather than adding the label later, so that every
	 * subsequent match of it can be label-scoped and use the :Subject(id) uniqueness index.
	 */
	private function updateNodeProperties( Subject $subject ): void {
		$this->transaction->run(
			'MERGE (n:Subject {id: $id}) SET n = $props',
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
			'MATCH (page:Page {id: $pageId, wiki_id: $wikiId}), (subject:Subject {id: $subjectId})
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
		$oldLabels = Neo4jNodeLabels::read( $this->transaction, $subject->id->text );
		$newLabels = [ 'Subject', $subject->getSchemaName()->getText() ];

		Neo4jNodeLabels::remove( $this->transaction, $subject->id->text, array_diff( $oldLabels, $newLabels ) );

		$labelsToAdd = array_diff( $newLabels, $oldLabels );

		if ( $labelsToAdd !== [] ) {
			$this->transaction->run(
				'MATCH (n:Subject {id: $id}) SET n:' . Cypher::buildLabelList( $labelsToAdd ),
				[ 'id' => $subject->id->text ]
			);
		}
	}

	private function updateRelations( Subject $subject, Schema $schema ): void {
		$updater = new Neo4jSubjectRelationUpdater(
			$subject->getId(),
			$subject->getTypedRelations( $schema ),
			$this->transaction,
			$this->wikiId
		);
		$updater->updateRelations();
	}

}
