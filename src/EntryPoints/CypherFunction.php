<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\Node;
use Parser;
use ProfessionalWiki\NeoWiki\Application\QueryEngine;
use ProfessionalWiki\NeoWiki\Presentation\TemplateRenderer;

class CypherFunction {

	public function __construct(
		private readonly QueryEngine $queryEngine,
		private readonly TemplateRenderer $templateRenderer,
	) {
	}

	/**
	 * @return array<mixed, mixed>
	 */
	public function handleParserFunctionCall( Parser $parser, string ...$arguments ): array {
		// TODO: check if argument exists

		$parser->getOutput()->addModules( [ 'ext.neowiki.editor' ] );

		$results = $this->queryEngine->runReadQuery( $arguments[0] )->getResults();

		return [
			$this->createMediaWikiHtmlTable( $results ) . $this->createJsonOutput( $results ),
			'noparse' => true,
			'isHTML' => true,
		];
	}

	private function createMediaWikiHtmlTable( CypherList $results ): string {
		return $this->templateRenderer->viewModelToString(
			'Table.html.twig',
			[
				'subjects' => $this->resultsToViewModel( $results ),
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

	private function createJsonOutput( CypherList $results ): string {
		return \Html::element(
			'pre',
			[],
			json_encode( $results->toRecursiveArray(), JSON_PRETTY_PRINT )
		);
	}

}
