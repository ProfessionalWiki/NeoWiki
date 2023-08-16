<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation\RunCypher;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\Queries\RunCypher\RunCypherError;
use ProfessionalWiki\NeoWiki\Application\Queries\RunCypher\RunCypherPresenter;
use ProfessionalWiki\NeoWiki\Presentation\TemplateRenderer;

class ParserFunctionRunCypherPresenter implements RunCypherPresenter {

	private string $html;
	private ?SummarizedResult $result = null;

	public function __construct(
		private readonly TemplateRenderer $templateRenderer,
		private readonly Format $presentationFormat,
	) {
	}

	public function presentError( RunCypherError $error ): void {
		$this->html = 'Cypher query is missing'; // TODO
	}

	public function presentSummarizedResult( SummarizedResult $result ): void {
		$this->result = $result;
	}

	public function getParserFunctionResponseStructure(): array {
		return $this->buildParserFunctionHtmlResponse( $this->buildHtml() );
	}

	private function buildHtml(): string {
		if ( isset( $this->html ) ) {
			return $this->html;
		}

		$result = $this->getSummarizedResult();

		// TODO: format interface and registry
		return match ( $this->presentationFormat ) {
			Format::TABULATOR_TABLE => ( new TableFormats( $this->templateRenderer ) )->createTabulatorTable( $result ),
			Format::DEBUG_JSON => ( new JsonFormat() )->createJsonOutput( $result ),
			Format::MEDIAWIKI_TABLE => ( new TableFormats( $this->templateRenderer ) )->createMediaWikiTable( $result ),
			Format::DEFAULT => ( new TableFormats( $this->templateRenderer ) )->createMediaWikiTable( $result ),
		};
	}

	private function getSummarizedResult(): SummarizedResult {
		if ( $this->result === null ) {
			throw new \LogicException();
		}

		return $this->result;
	}

	private function buildParserFunctionHtmlResponse( string $html ): array {
		return [
			$html,
			'noparse' => true,
			'isHTML' => true,
		];
	}

}
