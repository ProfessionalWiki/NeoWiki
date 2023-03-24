<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Laudis\Neo4j\Types\CypherList;
use Parser;
use ProfessionalWiki\NeoWiki\Application\QueryStore;

class CypherFunction {

	public function __construct(
		private readonly QueryStore $queryStore
	) {
	}

	public function handleParserFunctionCall( Parser $parser, string ...$arguments ): array {
		$results = $this->queryStore->runReadQuery( $arguments[0] )->getResults();

		return  [
			$this->createMediaWikiHtmlTable( $results ) . $this->createJsonOutput( $results ),
			'noparse' => true,
			'isHTML' => true,
		];
	}

	private function createMediaWikiHtmlTable( CypherList $results ): string {
		return \Html::element(
			'pre',
			[],
			json_encode( $results->toRecursiveArray(), JSON_PRETTY_PRINT )
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
