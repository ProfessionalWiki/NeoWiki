<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;

class VisJsOutput {

	public function buildHtmlForQueryResult( SummarizedResult $result ): string {
		return $this->buildHtml(
			id: uniqid( 'viz_' ),
			json: json_encode(
				$this->buildJsonArrayFromQueryResult( $result ),
				JSON_PRETTY_PRINT
			)
		);
	}

	private function buildHtml( string $id, string $json ): string {
		return <<<HTML
<div id="{$id}" class="NeoWikiQueryVis"></div>

<script type="text/javascript">
	if ( typeof window.NeoWikiVizData === "undefined" ) {
		window.NeoWikiVizData = {};
	}

	window.NeoWikiVizData['{$id}'] = {$json};
</script>
HTML;
	}

	public function buildJsonArrayFromQueryResult( SummarizedResult $result ): array {
		$nodes = [];
		$edges = [];

		/**
		 * @var CypherMap $record
		 */
		foreach ( $result as $record ) {
			foreach ( $record->values() as $value ) {
				if ( $value instanceof Node ) {
					$nodes[$value->getId()] = [
						'id' => $value->getId(),
						'label' => $value->getProperties()->get( 'name', $value->getProperties()->get( 'id', '' ) ),
						'title' => json_encode(
							[ 'labels' => $value->getLabels()->toArray(), 'properties' => $value->getProperties()->toArray() ],
							JSON_PRETTY_PRINT
						),
						'types' => $value->getLabels()->toArray(),
						'properties' => $value->getProperties()->toArray()
					];
				} elseif ( $value instanceof Relationship ) {
					$edges[] = [
						'from' => $value->getStartNodeId(),
						'to' => $value->getEndNodeId(),
						'arrows' => 'to',
						'label' => $value->getType(),
						'title' => json_encode( $value->getProperties()->toArray(), JSON_PRETTY_PRINT ),
						'properties' => $value->getProperties()->toArray(),
					];
				}
			}
		}

		return [
			'nodes' => array_values( $nodes ),
			'edges' => $edges
		];
	}

}
