<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction;

use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\CypherErrorMessage;

class CypherRawParserFunction {

	public function __construct(
		private readonly Neo4jQueryService $queryService,
	) {
	}

	/**
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	public function handle( Parser $parser, string $cypherQuery ): array {
		try {
			$result = $this->queryService->execute(
				new Neo4jQueryRequest(
					cypher: $cypherQuery,
					parameters: [],
					limits: Neo4jQueryLimits::forUser( RequestContext::getMain()->getUser() ),
				)
			);
		} catch ( QueryException $e ) {
			$message = CypherErrorMessage::for( $e );
			return $this->formatError( $parser->msg( $message->key, ...$message->params )->text() );
		}

		$json = json_encode( $result->rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( $json === false ) {
			return $this->formatError( $parser->msg( 'neowiki-cypher-raw-error-json-encode' )->text() );
		}

		return $this->formatResult( $json );
	}

	/**
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function formatResult( string $json ): array {
		return $this->asHtml( '<div class="mw-neowiki-cypher-result"><pre>' . htmlspecialchars( $json ) . '</pre></div>' );
	}

	/**
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function formatError( string $message ): array {
		return $this->asHtml( '<div class="error">' . htmlspecialchars( $message ) . '</div>' );
	}

	/**
	 * Hands the HTML to the parser as HTML rather than wikitext. Without this the text is parsed as
	 * wikitext, which autolinks any URL a returned value happens to hold: the trailing quote of the
	 * URL is swallowed into the link and percent-encoded, leaving invalid JSON with anchors in it.
	 * Error detail needs the same treatment, as the store's message can itself carry a URL.
	 *
	 * @return array{0: string, noparse: true, isHTML: true}
	 */
	private function asHtml( string $html ): array {
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

}
