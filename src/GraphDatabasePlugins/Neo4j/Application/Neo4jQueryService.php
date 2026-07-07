<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use Throwable;

readonly class Neo4jQueryService {

	public function __construct(
		private Neo4jReadQueryEngine $queryEngine,
		private CypherQueryValidator $validator,
		private Neo4jResultNormalizer $normalizer,
	) {
	}

	public function execute( Neo4jQueryRequest $request ): Neo4jQueryResult {
		$cypher = trim( $request->cypher );

		if ( $cypher === '' ) {
			throw new EmptyQueryException( 'Query is empty.' );
		}

		try {
			$allowed = $this->validator->queryIsAllowed( $cypher );
		} catch ( Neo4jException $e ) {
			// The EXPLAIN-based validator surfaces real Cypher errors (syntax, parameter, etc.)
			// the same way the engine does. Translate them so callers see the actual problem,
			// not a misleading "backend unavailable".
			throw $this->translateNeo4jException( $e );
		} catch ( Throwable $e ) {
			throw new BackendUnavailableException( $e->getMessage(), 0, $e );
		}

		if ( !$allowed ) {
			throw new WriteQueryRejectedException( 'Query is not read-only.' );
		}

		$startedAt = (int)( microtime( true ) * 1000 );

		try {
			$summarized = $this->queryEngine->runReadQuery(
				$cypher,
				$request->parameters,
				$request->limits->timeoutSeconds
			);
		} catch ( Neo4jException $e ) {
			throw $this->translateNeo4jException( $e );
		} catch ( Throwable $e ) {
			throw new BackendUnavailableException( $e->getMessage(), 0, $e );
		}

		$durationMs = (int)( microtime( true ) * 1000 ) - $startedAt;

		return $this->buildResult( $summarized, $request->limits->maxRows, $durationMs );
	}

	private function buildResult( SummarizedResult $rows, int $maxRows, int $durationMs ): Neo4jQueryResult {
		$total = $rows->count();
		$truncated = $total > $maxRows;

		$normalizedAll = $this->normalizer->convertRows( $rows );
		// convertRows returns a 1-indexed array; re-index as 0-based list and slice.
		$rowsList = array_values( $normalizedAll );
		if ( $truncated ) {
			$rowsList = array_slice( $rowsList, 0, $maxRows );
		}

		// Columns come from the protocol-level RETURN alias metadata, so they
		// are present even when the result has zero rows.
		return new Neo4jQueryResult(
			columns: $rows->keys(),
			rows: $rowsList,
			truncated: $truncated,
			resultCount: count( $rowsList ),
			durationMs: $durationMs,
		);
	}

	private function translateNeo4jException( Neo4jException $exception ): QueryException {
		// Prefer the Neo4j-supplied message ("Invalid input ...", "Expected parameter(s) ...")
		// over Laudis's verbose template ('Neo4j errors detected. First one with code "..." ...').
		$message = $exception->getNeo4jMessage() ?? $exception->getMessage();

		return match ( $exception->getNeo4jCode() ) {
			'Neo.ClientError.Transaction.TransactionTimedOut' => new QueryTimeoutException( $message, 0, $exception ),
			'Neo.ClientError.Statement.SyntaxError'           => new CypherSyntaxException( $message, 0, $exception ),
			'Neo.ClientError.Statement.ParameterMissing'      => new ParameterMissingException( $message, 0, $exception ),
			default                                            => new InternalQueryException( $message, 0, $exception ),
		};
	}

}
