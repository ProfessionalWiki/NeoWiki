<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction;

use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;

class CypherRawParserFunction {

	public function __construct(
		private readonly Neo4jQueryService $queryService,
	) {
	}

	public function handle( Parser $parser, string $cypherQuery ): string {
		try {
			$result = $this->queryService->execute(
				new Neo4jQueryRequest(
					cypher: $cypherQuery,
					parameters: [],
					limits: Neo4jQueryLimits::forUser( RequestContext::getMain()->getUser() ),
				)
			);
		} catch ( QueryException $e ) {
			return $this->formatError( $e->getMessage() );
		}

		$json = json_encode( $result->rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( $json === false ) {
			return $this->formatError( $parser->msg( 'neowiki-cypher-raw-error-json-encode' )->text() );
		}

		return $this->formatResult( $json );
	}

	private function formatResult( string $json ): string {
		return '<div class="mw-neowiki-cypher-result"><pre>' . htmlspecialchars( $json ) . '</pre></div>';
	}

	private function formatError( string $message ): string {
		return '<div class="error">' . htmlspecialchars( $message ) . '</div>';
	}

}
