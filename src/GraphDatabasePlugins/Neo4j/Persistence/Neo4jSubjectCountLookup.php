<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use ProfessionalWiki\NeoWiki\Application\SubjectCountLookup;

/**
 * Counts the Subjects in the Neo4j projection that a page holds: a Subject that several pages hold counts once, and
 * a stub, which no page holds, does not count.
 */
readonly class Neo4jSubjectCountLookup implements SubjectCountLookup {

	public function __construct(
		private ClientInterface $client,
		private string $wikiId,
	) {
	}

	public function getSubjectCountsBySchema(): array {
		return $this->client->readTransaction(
			function ( TransactionInterface $transaction ): array {
				// Only pages hold Subjects, so the degree says whether one does without reading the pages. Grouping by
				// the whole label list leaves picking the Schema label to once per group rather than once per Subject.
				$result = $transaction->run(
					'MATCH (subject:Subject {wiki_id: $wikiId})
					WHERE COUNT { (subject)<-[:HasSubject]-() } > 0
					RETURN labels(subject) AS labels, count(*) AS subjectCount',
					[ 'wikiId' => $this->wikiId ]
				);

				$counts = [];

				foreach ( $result as $row ) {
					/** @var list<string> $labels */
					$labels = $row->getAsCypherList( 'labels' )->toArray();
					$subjectCount = $row->getAsInt( 'subjectCount' );

					foreach ( array_diff( $labels, [ 'Subject' ] ) as $schemaName ) {
						$counts[$schemaName] = ( $counts[$schemaName] ?? 0 ) + $subjectCount;
					}
				}

				return $counts;
			}
		);
	}

}
