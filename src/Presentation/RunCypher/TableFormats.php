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
		private readonly array $columnsToInclude,
	) {
	}

	public function createMediaWikiTable( SummarizedResult $result ): string {
		return $this->templateRenderer->viewModelToString(
			'ResultTable.html.twig',
			$this->buildTwigViewModel( $result )
		);
	}

	public function createTabulatorTable( SummarizedResult $result ): string {
		return $this->templateRenderer->viewModelToString(
			'TableTabulator.html.twig',
			$this->buildTwigViewModel( $result )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildTwigViewModel( SummarizedResult $result ): array {
		return [
			'subjects' => $this->resultsToViewModel( $result->getResults() ),
			'includeId' => $this->columnsToInclude === [] || in_array( 'id', $this->columnsToInclude ),
		];
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
			'properties' => $this->getStatementsFromSubjectNode( $subject )
		];
	}

	private function getStatementsFromSubjectNode( Node $subject ): array {
		return array_map(
			fn( mixed $value ): array => [ 'values' => $this->normalizeValue( $value ) ],
			$this->filterOutStatementsWithPropertiesToExclude( $subject->getProperties()->toRecursiveArray() )
		);
	}

	private function filterOutStatementsWithPropertiesToExclude( array $statements ): array {
		unset( $statements['id'] );

		if ( $this->columnsToInclude === [] ) {
			return $statements;
		}

		return array_intersect_key(
			$statements,
			array_flip( $this->columnsToInclude )
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
