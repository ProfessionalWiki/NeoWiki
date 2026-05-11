<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryException;
use ProfessionalWiki\NeoWiki\Application\Query\QueryLimits;
use ProfessionalWiki\NeoWiki\Application\Query\QueryRequest;
use ProfessionalWiki\NeoWiki\Application\Query\QueryService;

class CypherRawParserFunction {

	public function __construct(
		private readonly QueryService $queryService,
	) {
	}

	public function handle( Parser $parser, string $cypherQuery ): string {
		try {
			$result = $this->queryService->execute(
				new QueryRequest(
					cypher: $cypherQuery,
					parameters: [],
					// TODO(task 7): replace with QueryLimits::forUser() once config + resolver land.
					limits: new QueryLimits( 30, 5000 ),
				)
			);
		} catch ( QueryException $e ) {
			return $this->formatError( $e->getMessage() );
		}

		$json = json_encode( $result->rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( $json === false ) {
			return $this->formatError( wfMessage( 'neowiki-cypher-raw-error-json-encode' )->text() );
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
