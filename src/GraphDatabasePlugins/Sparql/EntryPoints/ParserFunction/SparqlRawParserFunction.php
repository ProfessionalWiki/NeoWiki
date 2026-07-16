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

	/**
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	public function handle( Parser $parser, string $query ): array {
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

	/**
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function formatResult( string $json ): array {
		return $this->asHtml( '<div class="mw-neowiki-sparql-result"><pre>' . htmlspecialchars( $json ) . '</pre></div>' );
	}

	/**
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function formatError( string $message ): array {
		return $this->asHtml( '<div class="error">' . htmlspecialchars( $message ) . '</div>' );
	}

	/**
	 * Hands the HTML to the parser as HTML rather than wikitext. Without this the text is parsed as
	 * wikitext, which autolinks the IRIs a results document is full of: the trailing quote of each
	 * IRI is swallowed into the link and percent-encoded, leaving invalid JSON with anchors in it.
	 * Error detail needs the same treatment, as it carries the store's endpoint URL.
	 *
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function asHtml( string $html ): array {
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

}
