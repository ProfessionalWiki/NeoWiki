<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Query;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\QueryEngine;
use Throwable;

readonly class QueryService {

	public function __construct(
		private QueryEngine $queryEngine,
		private CypherQueryValidator $validator,
		private QueryResultNormalizer $normalizer,
	) {
	}

	public function execute( QueryRequest $request ): QueryResult {
		$cypher = trim( $request->cypher );

		if ( $cypher === '' ) {
			throw new EmptyQueryException( 'Query is empty.' );
		}

		if ( !$this->validator->queryIsAllowed( $cypher ) ) {
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

	private function buildResult( SummarizedResult $rows, int $maxRows, int $durationMs ): QueryResult {
		$total = $rows->count();
		$truncated = $total > $maxRows;

		$normalizedAll = $this->normalizer->convertRows( $rows );
		// convertRows returns a 1-indexed array; re-index as 0-based list and slice.
		$rowsList = array_values( $normalizedAll );
		if ( $truncated ) {
			$rowsList = array_slice( $rowsList, 0, $maxRows );
		}

		/** @var list<string> $columns */
		$columns = $rowsList === [] ? [] : array_keys( $rowsList[0] );

		return new QueryResult(
			columns: $columns,
			rows: $rowsList,
			truncated: $truncated,
			resultCount: count( $rowsList ),
			durationMs: $durationMs,
		);
	}

	private function translateNeo4jException( Neo4jException $exception ): QueryException {
		return match ( $exception->getNeo4jCode() ) {
			'Neo.ClientError.Transaction.TransactionTimedOut' => new QueryTimeoutException( $exception->getMessage(), 0, $exception ),
			'Neo.ClientError.Statement.SyntaxError'           => new CypherSyntaxException( $exception->getMessage(), 0, $exception ),
			'Neo.ClientError.Statement.ParameterMissing'      => new ParameterMissingException( $exception->getMessage(), 0, $exception ),
			default                                            => new BackendUnavailableException( $exception->getMessage(), 0, $exception ),
		};
	}

}
