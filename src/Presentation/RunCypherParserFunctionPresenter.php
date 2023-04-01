<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use Laudis\Neo4j\Databags\SummarizedResult;
use ProfessionalWiki\NeoWiki\Application\Queries\RunCypher\RunCypherError;
use ProfessionalWiki\NeoWiki\Application\Queries\RunCypher\RunCypherPresenter;

class RunCypherParserFunctionPresenter implements RunCypherPresenter {

	private string $html;
	private ?SummarizedResult $result = null;
	private ?array $viewModel = null;

	public function __construct(
		private readonly TemplateRenderer $templateRenderer
	) {
	}

	public function presentError( RunCypherError $error ): void {
		$this->html = 'Cypher query is missing'; // TODO
	}

	public function presentSummarizedResult( SummarizedResult $result ): void {
		$this->result = $result;
	}

	public function presentViewModel( array $viewModel ): void {
		$this->viewModel = $viewModel;
	}

	public function getParserFunctionResponseStructure(): array {
		return $this->buildParserFunctionHtmlResponse( $this->buildHtml() );
	}

	private function buildHtml(): string {
		if ( $this->html !== '' ) {
			return $this->html;
		}

		return $this->createMediaWikiHtmlTable() . $this->createJsonOutput();
	}

	private function createMediaWikiHtmlTable(): string {
		return $this->templateRenderer->viewModelToString(
			'Table.html.twig',
			[
				'subjects' => $this->viewModel
			]
		);
	}

	private function createJsonOutput(): string {
		return \Html::element(
			'pre',
			[],
			json_encode( $this->result->getResults()->toRecursiveArray(), JSON_PRETTY_PRINT )
		);
	}

	private function buildParserFunctionHtmlResponse( string $html ): array {
		return [
			$html,
			'noparse' => true,
			'isHTML' => true,
		];
	}

}
