<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use Exception;
use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\CypherQueryFilter;
use ProfessionalWiki\NeoWiki\Persistence\QueryEngine;

class CypherRawParserFunction {

	public function __construct(
		private readonly QueryEngine $queryEngine,
		private readonly CypherQueryFilter $queryFilter
	) {
	}

	public function handle( Parser $parser, string $cypherQuery ): string {
		$cypherQuery = trim( $cypherQuery );

		if ( $cypherQuery === '' ) {
			return $this->formatError( 'Empty Cypher query provided' );
		}

		if ( !$this->queryFilter->isReadQuery( $cypherQuery ) ) {
			return $this->formatError( 'Write queries are not allowed' );
		}

		try {
			$result = $this->queryEngine->runReadQuery( $cypherQuery );
			$jsonOutput = json_encode( $result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			return $this->formatCodeBlock( $jsonOutput );
		} catch ( Exception $e ) {
			return $this->formatError( 'Query execution failed: ' . $e->getMessage() );
		}
	}

	private function formatCodeBlock( string $content ): string {
		return "<pre><code class=\"json\">\n" . htmlspecialchars( $content ) . "\n</code></pre>";
	}

	private function formatError( string $message ): string {
		return "<div class=\"error\">" . htmlspecialchars( $message ) . "</div>";
	}

}
