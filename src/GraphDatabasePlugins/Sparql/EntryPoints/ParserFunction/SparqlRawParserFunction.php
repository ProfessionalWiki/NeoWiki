<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction;

use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\SparqlErrorMessage;

/**
 * {{#sparql_raw: <query> }} — outputs the W3C `application/sparql-results+json` document as pretty
 * JSON text, exactly like {{#cypher_raw}} renders its rows. The read-side sibling of
 * {@see \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction}.
 */
class SparqlRawParserFunction {

	public function __construct(
		private readonly SparqlQueryService $queryService,
	) {
	}

	public function handle( Parser $parser, string $query ): string {
		try {
			$result = $this->queryService->execute(
				new SparqlQueryRequest(
					sparql: $query,
					limits: SparqlQueryLimits::forUser( RequestContext::getMain()->getUser() ),
				)
			);
		} catch ( SparqlQueryException $e ) {
			$message = SparqlErrorMessage::for( $e );
			return $this->formatError( $parser->msg( $message->key, ...$message->params )->text() );
		}

		$json = json_encode( $result->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( $json === false ) {
			return $this->formatError( $parser->msg( 'neowiki-sparql-raw-error-json-encode' )->text() );
		}

		return $this->formatResult( $json );
	}

	private function formatResult( string $json ): string {
		return '<div class="mw-neowiki-sparql-result"><pre>' . htmlspecialchars( $json ) . '</pre></div>';
	}

	private function formatError( string $message ): string {
		return '<div class="error">' . htmlspecialchars( $message ) . '</div>';
	}

}
