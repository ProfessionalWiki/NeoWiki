<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\ReferencingSubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

class Neo4jReferencingSubjectLookup implements ReferencingSubjectLookup {

	public function __construct(
		private readonly ClientInterface $client,
		private readonly string $wikiId,
	) {
	}

	/**
	 * @return SubjectId[]
	 */
	public function getIdsOfSubjectsReferencing( SubjectId $target, int $limit ): array {
		return array_map(
			static fn ( string $id ): SubjectId => new SubjectId( $id ),
			$this->fetchIds( $target->text, $limit )
		);
	}

	/**
	 * Each source is reached through a Page owned by the current wiki, and carries this wiki's
	 * wiki_id itself, so neither a Subject stamped for another wiki nor one a foreign Page holds is
	 * returned (ADR 22). The Page anchor also drops stub nodes, which a relation into a Subject that
	 * does not exist yet creates and which no Page holds.
	 *
	 * Ordered by the name each Subject is shown under. A Subject nobody labelled carries no `name`
	 * at all unless it is its page's Main Subject, and Neo4j sorts a missing one last, so sorting on
	 * the bare property would queue every unlabelled referrer behind every labelled one and cut them
	 * first. Its Schema is the name it is shown under, and the node's other label is its Schema.
	 *
	 * @return list<string>
	 */
	private function fetchIds( string $targetId, int $limit ): array {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ) use ( $targetId, $limit ): array {
				/**
				 * @var SummarizedResult $result
				 */
				$result = $transaction->run(
					'MATCH (page:Page { wiki_id: $wikiId })-[:HasSubject]->(source:Subject)
						-[]->(target:Subject { id: $targetId })
					 WHERE source.wiki_id = $wikiId AND source.id <> $targetId
					 RETURN DISTINCT source.id AS id,
						coalesce( source.name, head( [ l IN labels( source ) WHERE l <> "Subject" ] ) ) AS name
					 ORDER BY name, id
					 LIMIT $limit',
					[
						'targetId' => $targetId,
						'limit' => $limit,
						'wikiId' => $this->wikiId,
					]
				);

				$ids = [];

				foreach ( $result as $row ) {
					$ids[] = $row->get( 'id' );
				}

				return $ids;
			}
		);
	}

}
