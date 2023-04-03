<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Parser;
use ProfessionalWiki\NeoWiki\Application\Queries\RunCypher\RunCypherQuery;
use ProfessionalWiki\NeoWiki\Application\QueryEngine;
use ProfessionalWiki\NeoWiki\Presentation\RunCypherParserFunctionPresenter;
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
		$presenter = new RunCypherParserFunctionPresenter( $this->templateRenderer );

		$cypherQueryQuery = new RunCypherQuery(
			$presenter,
			$this->queryEngine,
		);

		$cypherQueryQuery->runCypher( $arguments[0] );

		return $presenter->getParserFunctionResponseStructure();
	}

}
