<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\MediaWiki\Persistence\Neo4j;

use DateTime;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\QueryEngine;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\QueryStore;
use ProfessionalWiki\NeoWiki\MediaWiki\Persistence\WriteQueryEngine;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class Neo4jQueryStore implements QueryStore, QueryEngine, WriteQueryEngine {

	public function __construct(
		private ClientInterface $client,
		private ClientInterface $readOnlyClient,
		private SubjectUpdaterFactory $subjectUpdaterFactory,
	) {
	}

	public function savePage( Page $page ): void {
		$this->client->writeTransaction( function ( TransactionInterface $transaction ) use ( $page ): void {
			$transaction->run(
				'
				// Create or update the page
				MERGE (page:Page {id: $pageId})
				SET page.id = $pageId,
					page.name = $pageTitle,
					page.creationTime = datetime($creationTime),
					page.lastUpdated = datetime($modificationTime),
					page.lastEditor = $lastEditor,
					page.categories = $categories

				// Delete subjects that are no longer present on the page
				WITH page
				MATCH (page)-[r:HasSubject]->(subject)
				WHERE NOT subject.id IN $subjectIds
				DETACH DELETE subject

				// Remove all existing HasSubject relations
				WITH page
				MATCH (page)-[r:HasSubject]->()
				DELETE r
				',
				[
					'pageId' => $page->getId()->id,
					'subjectIds' => $page->getSubjects()->getAllSubjects()->getIdsAsTextArray(),
					'pageTitle' => $page->getProperties()->title,
					'creationTime' => self::mediaWikiTimestampToNeo4jFormat( $page->getProperties()->creationTime ),
					'modificationTime' => self::mediaWikiTimestampToNeo4jFormat( $page->getProperties()->modificationTime ),
					'categories' => $page->getProperties()->categories, // TODO: turn into relations
					'lastEditor' => $page->getProperties()->lastEditor, // TODO: turn into relation?
				]
			);

			$this->updateSubjects( $transaction, $page );
		} );
	}

	public static function mediaWikiTimestampToNeo4jFormat( string $timestamp ): string {
		$date = DateTime::createFromFormat( 'YmdHis', $timestamp );

		if ( $date === false ) {
			return '';
		}

		return $date->format( 'Y-m-d\TH:i:s' );
	}

	private function updateSubjects( TransactionInterface $transaction, Page $page ): void {
		$updater = $this->subjectUpdaterFactory->newSubjectUpdater( $transaction, $page->getId() );

		$mainSubject = $page->getSubjects()->getMainSubject();

		if ( $mainSubject !== null ) {
			$updater->updateSubject( $mainSubject, isMainSubject: true );
		}

		foreach ( $page->getSubjects()->getChildSubjects()->asArray() as $subject ) {
			$updater->updateSubject( $subject, isMainSubject: false );
		}
	}

	public function deletePage( PageId $pageId ): void {
		$this->client->writeTransaction( function ( TransactionInterface $transaction ) use ( $pageId ): void {
			foreach ( $this->getSubjectIdsByPageId( $transaction, $pageId ) as $subjectId ) {
				$this->deleteSubject( $transaction, new SubjectId( $subjectId ) );
			}

			$this->deletePageNode( $transaction, $pageId );
		} );
	}

	private function deletePageNode( TransactionInterface $transaction, PageId $pageId ): void {
		// TODO: Redlinks: page should not always be deleted due to incoming links? Difference between ID and title in meaning
		$transaction->run(
			'MATCH (page:Page {id: $pageId}) DETACH DELETE page',
			[ 'pageId' => $pageId->id ]
		);
	}

	/**
	 * FIXME: tests still pass if this function returns an empty array
	 * @return string[]
	 */
	private function getSubjectIdsByPageId( TransactionInterface $transaction, PageId $pageId ): array {
		/**
		 * @var SummarizedResult $results
		 */
		$results = $transaction->run(
			'MATCH (page:Page {id: $pageId})-[:HasSubject]->(subject:Subject)
				RETURN subject.id AS id, subject AS properties, labels(subject) AS labels',
			[ 'pageId' => $pageId->id ]
		);

		return array_map(
			fn( $record ) => $record->get('id'),
			$results->toArray()
		);
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
		return $this->readOnlyClient->readTransaction(
			function ( TransactionInterface $transaction ) use ( $cypher ): SummarizedResult {
				return $transaction->run( $cypher );
			}
		);
	}

	public function runWriteQuery( string $cypher ): SummarizedResult {
		return $this->client->writeTransaction(
			function ( TransactionInterface $transaction ) use ( $cypher ): SummarizedResult {
				return $transaction->run( $cypher );
			}
		);
	}

}
