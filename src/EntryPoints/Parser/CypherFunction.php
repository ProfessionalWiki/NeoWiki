<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Parser;

use Parser;
use ProfessionalWiki\NeoWiki\Application\Queries\RunCypher\RunCypherQuery;
use ProfessionalWiki\NeoWiki\Application\QueryEngine;
use ProfessionalWiki\NeoWiki\Presentation\RunCypher\Format;
use ProfessionalWiki\NeoWiki\Presentation\RunCypher\ParserFunctionRunCypherPresenter;
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
		$parser->getOutput()->addModules( [ 'ext.neowiki.table-editor' ] );

		$presenter = new ParserFunctionRunCypherPresenter(
			templateRenderer: $this->templateRenderer,
			presentationFormat: Format::fromString( $arguments[1] ?? Format::DEFAULT->value ),
			columnsToInclude: $this->getColumnsToIncludeFromArgumentArray( $arguments ),
		);

		$cypherQueryQuery = new RunCypherQuery(
			$presenter,
			$this->queryEngine,
		);

		$cypherQueryQuery->runCypher( $arguments[0] );

		return $presenter->getParserFunctionResponseStructure();
	}

	private function getColumnsToIncludeFromArgumentArray( array $arguments ): array {
		if ( isset( $arguments[2] ) && trim( $arguments[2] ) !== '' ) {
			return explode( ',', $arguments[2] );
		}

		return [];
	}

}
