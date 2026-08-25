<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use Psr\Log\LoggerInterface;

/**
 * Writes the Subjects of one page to the graph, with one query per concern rather than per Subject.
 */
class Neo4jSubjectUpdater {

	public function __construct(
		private readonly TransactionInterface $transaction,
		private readonly PageId $pageId,
		private readonly SchemaLookup $schemaRepository,
		private readonly Neo4jValueBuilderRegistry $valueBuilderRegistry,
		private readonly LoggerInterface $logger,
		private readonly string $wikiId,
		private readonly Neo4jOrphanCandidates $orphanCandidates,
	) {
	}

	public function updateSubjects( Page $page ): void {
		$subjects = $this->resolveSchemas( $page->getSubjects() );

		if ( $subjects === [] ) {
			return;
		}

		// updateNodeProperties must precede updateHasSubjectRelations and updateNodeLabels: those two
		// only MATCH the subjects' nodes, and this is the step that creates them. Move it after them
		// and a subject with no relations silently loses its page link and its Schema label.
		$currentLabels = $this->updateNodeProperties( $subjects, $page->getProperties()->getName() );

		$this->updateRelations( $subjects );
		$this->updateHasSubjectRelations( $subjects );
		$this->updateNodeLabels( $subjects, $currentLabels );
	}

	/**
	 * Subjects whose Schema cannot be found are left out of the write: without it their Statements
	 * cannot be read, and there is nothing to project.
	 *
	 * @return Neo4jPageSubject[]
	 */
	private function resolveSchemas( PageSubjects $pageSubjects ): array {
		$mainSubjectId = $pageSubjects->getMainSubject()?->id->text;
		$subjects = [];

		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			$schema = $this->schemaRepository->getSchema( $subject->getSchemaName() );

			if ( $schema === null ) {
				$this->logger->warning( 'Schema not found: ' . $subject->getSchemaName()->getText() );
				continue;
			}

			$subjects[] = new Neo4jPageSubject( $subject, $schema, $subject->id->text === $mainSubjectId );
		}

		return $subjects;
	}

	/**
	 * Creates the nodes with the :Subject label: the later steps of the save match them by that label.
	 * Returns the labels each node carries now, which the label reconciliation needs and which nothing
	 * between here and there changes.
	 *
	 * @param Neo4jPageSubject[] $subjects
	 * @return array<string, string[]>
	 */
	private function updateNodeProperties( array $subjects, string $pageName ): array {
		/**
		 * @var SummarizedResult $result
		 */
		$result = $this->transaction->run(
			'UNWIND $subjects AS row
				MERGE (node:Subject {id: row.id})
				SET node = row.properties
				RETURN row.id AS id, labels(node) AS labels',
			[
				'subjects' => array_map(
					fn ( Neo4jPageSubject $pageSubject ) => [
						'id' => $pageSubject->getId(),
						'properties' => $this->nodeProperties( $pageSubject, $pageName ),
					],
					$subjects
				),
			]
		);

		$labels = [];

		foreach ( $result->toArray() as $record ) {
			$labels[$record->get( 'id' )] = $record->get( 'labels' )->toArray();
		}

		return $labels;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function nodeProperties( Neo4jPageSubject $pageSubject, string $pageName ): array {
		$subject = $pageSubject->subject;

		$properties = $this->statementsToNodeProperties( $subject->getStatements() );

		// Assigned rather than array_merge()d: a property named like a decimal integer is an int
		// key by then, and array_merge() renumbers those, which would file its value under a
		// different property name.
		$properties['id'] = $subject->id->text;
		$properties['wiki_id'] = $this->wikiId;

		$name = $this->nodeName( $pageSubject, $pageName );

		if ( $name === null ) {
			// Explicit, because a Property Name may collide with a fixed one and must not win it.
			unset( $properties['name'] );
		}
		else {
			$properties['name'] = $name;
		}

		return $properties;
	}

	/**
	 * The name a Subject node carries: the tiers the graph materializes, which a page move keeps
	 * current through the reprojection it already triggers.
	 */
	private function nodeName( Neo4jPageSubject $pageSubject, string $pageName ): ?string {
		return SubjectDisplayName::labelOrPageName(
			$pageSubject->subject->getLabel(),
			$pageSubject->isMainSubject,
			$pageName
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

	/**
	 * @param Neo4jPageSubject[] $subjects
	 */
	private function updateRelations( array $subjects ): void {
		$relations = [];

		foreach ( $subjects as $pageSubject ) {
			$relations[$pageSubject->getId()] = $pageSubject->subject->getTypedRelations( $pageSubject->schema );
		}

		( new Neo4jPageRelationUpdater( $this->transaction, $this->wikiId, $this->orphanCandidates ) )
			->updateRelations( $relations );
	}

	/**
	 * @param Neo4jPageSubject[] $subjects
	 */
	private function updateHasSubjectRelations( array $subjects ): void {
		$this->transaction->run(
			'MATCH (page:Page {id: $pageId, wiki_id: $wikiId})
				UNWIND $subjects AS row
				MATCH (subject:Subject {id: row.id})
				MERGE (page)-[:HasSubject {isMain: row.isMain}]->(subject)',
			[
				'pageId' => $this->pageId->id,
				'wikiId' => $this->wikiId,
				'subjects' => array_map(
					fn ( Neo4jPageSubject $pageSubject ) => [
						'id' => $pageSubject->getId(),
						'isMain' => $pageSubject->isMainSubject,
					],
					$subjects
				),
			]
		);
	}

	/**
	 * @param Neo4jPageSubject[] $subjects
	 * @param array<string, string[]> $currentLabels
	 */
	private function updateNodeLabels( array $subjects, array $currentLabels ): void {
		$labelsToRemove = [];
		$labelsToAdd = [];

		foreach ( $subjects as $pageSubject ) {
			$oldLabels = $currentLabels[$pageSubject->getId()] ?? [];
			$newLabels = [ 'Subject', $pageSubject->subject->getSchemaName()->getText() ];

			$labelsToRemove[$pageSubject->getId()] = array_diff( $oldLabels, $newLabels );
			$labelsToAdd[$pageSubject->getId()] = array_diff( $newLabels, $oldLabels );
		}

		Neo4jNodeLabels::remove( $this->transaction, $labelsToRemove );
		Neo4jNodeLabels::add( $this->transaction, $labelsToAdd );
	}

}
