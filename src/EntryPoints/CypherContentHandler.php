<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Content;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class CypherContentHandler extends \TextContentHandler {

	protected function getContentClass(): string {
		return CypherContent::class;
	}

	public function makeEmptyContent(): CypherContent {
		return new CypherContent( '' );
	}

	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$parserOutput
	): void {
		if ( !$cpoParams->getGenerateHtml() ) {
			$parserOutput->setText( null );
			return;
		}

		if ( !( $content instanceof CypherContent ) || !$content->isValid() ) {
			$parserOutput->setText( wfMessage( 'neowiki-invalid-cypher-query' )->parse() ); // TODO: define message
			return;
		}

		$this->outputVisualization(
			$parserOutput,
			NeoWikiExtension::getInstance()->getQueryStore()->runReadQuery( $content->getText() )
		);
	}

	private function outputVisualization( ParserOutput &$parserOutput, SummarizedResult $queryResult ): void {
		// TODO: show message if query result is empty

		$parserOutput->addModules( [ 'ext.neowiki.visjs' ] );

		$json = json_encode( $this->convertToVisJsData( $queryResult ), JSON_PRETTY_PRINT );

		$id = uniqid( 'viz_' );

		$parserOutput->setText(
			<<<HTML
<style type="text/css">
	.NeoWikiQueryViz {
		width: 85vw;
		height: 80vh;
		border: 1px solid lightgray;
		font: 22pt arial;
	}
</style>

<div id="{$id}" class="NeoWikiQueryViz"></div>

<script type="text/javascript">
	if ( typeof window.NeoWikiVizData === "undefined" ) {
		window.NeoWikiVizData = {};
	}

	window.NeoWikiVizData['{$id}'] = {$json};
</script>
HTML
		);
	}

	private function convertToVisJsData( SummarizedResult $result ): array {
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
						'labels' => $value->getLabels()->toArray(),
						'properties' => $value->getProperties()->toArray()
					];
				} elseif ( $value instanceof Relationship ) {
					$edges[] = [
						'from' => $value->getStartNodeId(),
						'to' => $value->getEndNodeId(),
						'label' => $value->getType(),
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
