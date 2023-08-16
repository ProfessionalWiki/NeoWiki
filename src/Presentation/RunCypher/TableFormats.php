<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation\RunCypher;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Node;
use ProfessionalWiki\NeoWiki\Presentation\TemplateRenderer;

class TableFormats {

	public function __construct(
		private readonly TemplateRenderer $templateRenderer,
	) {
	}

	public function createMediaWikiTable( SummarizedResult $result ): string {
		return $this->templateRenderer->viewModelToString(
			'ResultTable.html.twig',
			[
				'subjects' => $this->resultsToViewModel( $result->getResults() )
			]
		);
	}

	public function createTabulatorTable( SummarizedResult $result ): string {
		return $this->templateRenderer->viewModelToString(
			'TableTabulator.html.twig',
			[
				'subjects' => $this->resultsToViewModel( $result->getResults() )
			]
		);
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
			fn( mixed $value ): array => [ 'values' => $this->normalizeValue( $value ) ],
			array_diff_key(
				$subject->getProperties()->toRecursiveArray(),
				[ 'id' => '' ]
			)
		);
	}

	private function normalizeValue( mixed $value ): array {
		if ( $value instanceof DateTime ) {
			return [ $value->toDateTime()->format( 'Y-m-d H:i:s' ) ];
		}

		return (array)$value;
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
