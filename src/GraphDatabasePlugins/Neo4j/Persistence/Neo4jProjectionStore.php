<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use DateTime;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use ProfessionalWiki\NeoWiki\Domain\Page\PageValue;
use ProfessionalWiki\NeoWiki\Domain\Page\PageValueType;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

readonly class Neo4jProjectionStore implements GraphDatabasePlugin {

	public function __construct(
		private ClientInterface $client,
		private Neo4jSubjectUpdaterFactory $subjectUpdaterFactory,
		private string $wikiId,
	) {
	}

	public function savePage( Page $page ): void {
		$this->client->writeTransaction( function ( TransactionInterface $transaction ) use ( $page ): void {
			$this->upsertPageNode( $transaction, $page );
			$this->removeAbsentSubjects( $transaction, $page );
			$this->detachSubjectsFromPage( $transaction, $page->getId() );
			$this->updateSubjects( $transaction, $page );
		} );
	}

	private function upsertPageNode( TransactionInterface $transaction, Page $page ): void {
		$properties = $page->getProperties()->asArray();
		[ $typedSetClauses, $typedParams, $properties ] = $this->extractTypedValues( $properties );

		// Create or update the page. Page identity is scoped per wiki so that pages from different
		// wikis sharing the same id do not collide in a shared graph. The wiki_id is persisted by
		// the MERGE pattern itself.
		$cypher = '
			MERGE (page:Page {id: $pageId, wiki_id: $wikiId})
			SET page += $properties
			SET page.id = $pageId';

		if ( $typedSetClauses !== '' ) {
			$cypher .= ',' . $typedSetClauses;
		}

		$transaction->run(
			$cypher,
			array_merge(
				[
					'pageId' => $page->getId()->id,
					'wikiId' => $this->wikiId,
					'properties' => new CypherMap( $properties ),
				],
				$typedParams,
			)
		);
	}

	/**
	 * Removes the subjects that are attached to the page in the graph but are no longer present on the
	 * page. This reuses deleteSubject so that a removed subject still referenced by other subjects is
	 * kept as a stub instead of being deleted, keeping the incoming relations valid.
	 */
	private function removeAbsentSubjects( TransactionInterface $transaction, Page $page ): void {
		$presentSubjectIds = $page->getSubjects()->getAllSubjects()->getIdsAsTextArray();

		foreach ( $this->getSubjectIdsByPageId( $transaction, $page->getId() ) as $attachedSubjectId ) {
			if ( !in_array( $attachedSubjectId, $presentSubjectIds, true ) ) {
				$this->deleteSubject( $transaction, new SubjectId( $attachedSubjectId ) );
			}
		}
	}

	private function detachSubjectsFromPage( TransactionInterface $transaction, PageId $pageId ): void {
		$transaction->run(
			'MATCH (page:Page {id: $pageId, wiki_id: $wikiId})-[hasSubject:HasSubject]->()
				DELETE hasSubject',
			[ 'pageId' => $pageId->id, 'wikiId' => $this->wikiId ]
		);
	}

	/**
	 * Extracts PageValue instances from the property map and converts them
	 * to Cypher SET clauses with parameterized values.
	 *
	 * @param array<string, mixed> $properties
	 * @return array{ string, array<string, mixed>, array<string, mixed> }
	 *         [ setClauses, params, remainingProperties ]
	 */
	private function extractTypedValues( array $properties ): array {
		$setClauses = '';
		$params = [];

		foreach ( $properties as $key => $value ) {
			if ( !( $value instanceof PageValue ) ) {
				continue;
			}

			$paramName = "typed_$key";

			$setClauses .= match ( $value->getType() ) {
				PageValueType::Datetime => "\n\t\t\t\t\tpage.$key = datetime(\$$paramName),",
			};

			/** @var string $rawValue */
			$rawValue = $value->getValue();

			$params[$paramName] = match ( $value->getType() ) {
				PageValueType::Datetime => self::mediaWikiTimestampToNeo4jFormat( $rawValue ),
			};

			unset( $properties[$key] );
		}

		return [ rtrim( $setClauses, ',' ), $params, $properties ];
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
			'MATCH (page:Page {id: $pageId, wiki_id: $wikiId}) DETACH DELETE page',
			[ 'pageId' => $pageId->id, 'wikiId' => $this->wikiId ]
		);
	}

	/**
	 * @return string[]
	 */
	private function getSubjectIdsByPageId( TransactionInterface $transaction, PageId $pageId ): array {
		/**
		 * @var SummarizedResult $results
		 */
		$results = $transaction->run(
			'MATCH (page:Page {id: $pageId, wiki_id: $wikiId})-[:HasSubject]->(subject:Subject)
				RETURN subject.id AS id, subject AS properties, labels(subject) AS labels',
			[ 'pageId' => $pageId->id, 'wikiId' => $this->wikiId ]
		);

		return array_map(
			fn( $record ) => $record->get( 'id' ),
			$results->toArray()
		);
	}

	private function deleteSubject( TransactionInterface $transaction, SubjectId $subjectId ): void {
		if ( $this->subjectHasIncomingRelations( $transaction, $subjectId ) ) {
			$this->reduceSubjectToStub( $transaction, $subjectId );
		}
		else {
			$transaction->run(
				'MATCH (subject {id: $subjectId})
				DETACH DELETE subject',
				[ 'subjectId' => $subjectId->text ]
			);
		}
	}

	/**
	 * Reduces a subject node to a stub: it is detached from its page and its outgoing relations, and
	 * stripped down to only the id and wiki_id properties and the Subject label. The incoming relations
	 * from other subjects are kept, so the stub keeps those references valid. The stub is upgraded back
	 * to a full subject in place if the subject is saved again.
	 */
	private function reduceSubjectToStub( TransactionInterface $transaction, SubjectId $subjectId ): void {
		$transaction->run(
			'MATCH (subject {id: $subjectId})
				OPTIONAL MATCH ()-[hasSubject:HasSubject]->(subject)
				OPTIONAL MATCH (subject)-[outgoingRelation]->()
				DELETE hasSubject, outgoingRelation
				SET subject = {id: $subjectId, wiki_id: $wikiId}
				SET subject:Subject',
			[ 'subjectId' => $subjectId->text, 'wikiId' => $this->wikiId ]
		);

		$this->removeNonStubLabels( $transaction, $subjectId );
	}

	private function removeNonStubLabels( TransactionInterface $transaction, SubjectId $subjectId ): void {
		Neo4jNodeLabels::remove(
			$transaction,
			$subjectId->text,
			array_diff( Neo4jNodeLabels::read( $transaction, $subjectId->text ), [ 'Subject' ] )
		);
	}

	private function subjectHasIncomingRelations( TransactionInterface $transaction, SubjectId $subjectId ): bool {
		return $transaction->run(
			'MATCH (subject {id: $subjectId})<-[incomingRelation]-()
			WHERE NOT incomingRelation:HasSubject
			RETURN incomingRelation',
			[ 'subjectId' => $subjectId->text ]
		)->isEmpty() === false;
	}

}
