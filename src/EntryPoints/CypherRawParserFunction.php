<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Exception;
use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Persistence\QueryEngine;
use RuntimeException;

class CypherRawParserFunction {

	public function __construct(
		private readonly QueryEngine $queryEngine,
		private readonly CypherQueryValidator $queryFilter
	) {
	}

	public function handle( Parser $parser, string $cypherQuery ): string {
		$cypherQuery = trim( $cypherQuery );

		if ( $cypherQuery === '' ) {
			return $this->formatError( wfMessage( 'neowiki-cypher-raw-error-empty-query' )->text() );
		}

		try {
			if ( !$this->queryFilter->queryIsAllowed( $cypherQuery ) ) {
				return $this->formatError( wfMessage( 'neowiki-cypher-raw-error-write-query' )->text() );
			}

			$result = $this->queryEngine->runReadQuery( $cypherQuery );
			$jsonOutput = json_encode( $result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			if ( $jsonOutput === false ) {
				throw new RuntimeException( wfMessage( 'neowiki-cypher-raw-error-json-encode' )->text() );
			}

			return $this->formatCodeBlock( $jsonOutput );
		} catch ( Exception $e ) {
			return $this->formatError( wfMessage( 'neowiki-cypher-raw-error-query-failed', $e->getMessage() )->text() );
		}
	}

	private function formatCodeBlock( string $content ): string {
		return '<pre><code class="json">' . "\n" . htmlspecialchars( $content ) . "\n" . '</code></pre>';
	}

	private function formatError( string $message ): string {
		return '<div class="error">' . htmlspecialchars( $message ) . '</div>';
	}

}
