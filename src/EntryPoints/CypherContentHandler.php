<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Content;
use Laudis\Neo4j\Databags\SummarizedResult;
use MediaWiki\Content\Renderer\ContentParseParams;
use ParserOutput;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
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

//		$parserOutput->addModules( [ 'ext.neowiki.visjs' ] );
//
//		$visJs = new VisJsOutput();
//
//		$parserOutput->setText( $visJs->buildHtmlForQueryResult( $queryResult ) );

		$parserOutput->addModules( [ 'ext.neowiki.popoto' ] );

		$parserOutput->setText(
			<<<HTML
<section class="ppt-section-main" style="height: 700px">
    <div class="ppt-section-header">
        <span class="ppt-header-span">Neo4j movie graph -</span> Results example
    </div>

    <div class="ppt-container-graph">

        <div id="popoto-taxonomy" class="ppt-taxo-nav">

        </div>

        <div id="popoto-graph" class="ppt-div-graph">
            <!-- Graph is generated here -->
        </div>
    </div>

    <div id="popoto-cypher" class="ppt-container-query">
        <!-- Query viewer is generated here -->
    </div>

    <!-- Add a header with total number of results count -->
    <div class="ppt-section-header">
        RESULTS <span id="rescount" class="ppt-count"></span>
    </div>

    <div id="popoto-results" class="ppt-container-results">
        <!-- Results are generated here -->
    </div>

</section>
HTML
		);
	}

}
