<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Content;
use Laudis\Neo4j\Databags\SummarizedResult;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\PopotoOutput;
use ProfessionalWiki\NeoWiki\Presentation\VisJsOutput;

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
		$parserOutput->addModules( [ 'ext.neowiki.popoto' ] );

		$visJs = new VisJsOutput();
		$popoto = new PopotoOutput();

		$parserOutput->setText( $visJs->buildHtmlForQueryResult( $queryResult ) . $popoto->buildHtml() );
	}

}
