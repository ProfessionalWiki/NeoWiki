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

	/**
	 * @param string[] $columnsToInclude
	 */
	public function __construct(
		private readonly TemplateRenderer $templateRenderer,
		private readonly Format $presentationFormat,
		private readonly array $columnsToInclude,
	) {
	}

	public function presentError( RunCypherError $error ): void {
		$this->html = match( $error ) {
			RunCypherError::NOT_A_READ_QUERY => 'Cypher query is not read-only',
			default => 'Cypher query is missing' // TODO
		};
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

		$tableFormats = new TableFormats( $this->templateRenderer, $this->columnsToInclude );

		// TODO: format interface and registry
		return match ( $this->presentationFormat ) {
			Format::TABULATOR_TABLE => $tableFormats->createTabulatorTable( $result ),
			Format::DEBUG_JSON => ( new JsonFormat() )->createJsonOutput( $result ),
			Format::MEDIAWIKI_TABLE => $tableFormats->createMediaWikiTable( $result ),
			Format::DEFAULT => $tableFormats->createMediaWikiTable( $result ),
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
