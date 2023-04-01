<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\RunCypher;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\Node;
use ProfessionalWiki\NeoWiki\Application\QueryEngine;

class RunCypherQuery {

	public function __construct(
		private readonly QueryEngine $queryEngine,
		private readonly RunCypherPresenter $presenter
	) {
	}

	public function runCypher( string $cypherQuery ): void {
		if ( trim( $cypherQuery ) === '' ) {
			$this->presenter->presentError( RunCypherError::NO_QUERY );
			return;
		}

		$result = $this->queryEngine->runReadQuery( $cypherQuery );

		$this->presenter->presentSummarizedResult( $result );
		$this->presenter->presentViewModel( $this->resultsToViewModel( $result->getResults() ) );
	}

	private function resultsToViewModel( CypherList $results ): array {
		$subjects = [];

		foreach ( $results->toRecursiveArray() as $result ) {
			foreach ( $result as $varValue ) {
				if ( $varValue instanceof Node && $this->isSubjectNode( $varValue ) ) {
					$subjects[] = $this->subjectNodeToArray( $varValue );
				}
			}
		}

		return $this->defaultEmptyProperties( $subjects );
	}

	private function isSubjectNode( Node $node ): bool {
		return $node->getProperties()->hasKey( 'id' ); // TODO: make this more robust
	}

	private function subjectNodeToArray( Node $subject ): array {
		return [
			'id' => $subject->getProperties()->get( 'id' ),
			'types' => $subject->getLabels(),
			'properties' => $this->getPropertiesFromSubjectNode( $subject )
		];
	}

	private function getPropertiesFromSubjectNode( Node $subject ): array {
		return array_map(
			fn( mixed $value ): array => [ 'values' => (array)$value ],
			array_diff_key(
				$subject->getProperties()->toRecursiveArray(),
				[ 'id' => '' ]
			)
		);
	}

	private function defaultEmptyProperties( array $subjects ): array {
		$propertyNames = $this->getAllPropertyNames( $subjects );

		return array_map(
			function( array $subject ) use ( $propertyNames ): array {
				$subject['properties'] = array_merge(
					array_fill_keys( $propertyNames, [ 'values' => [] ] ),
					$subject['properties']
				);

				return $subject;
			},
			$subjects
		);
	}

	private function getAllPropertyNames( array $subjects ): array {
		return array_unique(
			array_reduce(
				$subjects,
				function( array $carry, array $subject ): array {
					return array_merge( $carry, array_keys( $subject['properties'] ) );
				},
				[]
			)
		);
	}

}
