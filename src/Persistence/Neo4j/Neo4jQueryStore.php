<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\Neo4j;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\QueryEngine;
use ProfessionalWiki\NeoWiki\Application\QueryStore;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class Neo4jQueryStore implements QueryStore, QueryEngine {

	public function __construct(
		private ClientInterface $client,
	) {
	}

	public function savePage( Page $page ): void {
		$this->client->writeTransaction( function ( TransactionInterface $transaction ) use ( $page ): void {
			$transaction->run(
				'
				// Create or update the page
				MERGE (page:Page {id: $pageId})
				SET page.id = $pageId, page.name = $pageTitle, page.lastUpdated = datetime()

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
					'pageTitle' => $page->getProperties()->title,
					'subjectIds' => $page->getSubjects()->getAllSubjects()->getIdsAsTextArray(),
				]
			);

			$this->updateSubjects( $transaction, $page );
		} );
	}

	private function updateSubjects( TransactionInterface $transaction, Page $page ): void {
		$updater = new SubjectUpdater( $transaction, $page->getId() );

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
	 * @return string[]
	 */
	private function getSubjectIdsByPageId( TransactionInterface $transaction, PageId $pageId ): array {
		/**
		 * @var SummarizedResult $result
		 */
		$result = $transaction->run(
			'MATCH (page:Page {id: $pageId})-[:HasSubject]->(subject:Subject)
				RETURN subject.id AS id, subject AS properties, labels(subject) AS labels',
			[ 'pageId' => $pageId->id ]
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

}
