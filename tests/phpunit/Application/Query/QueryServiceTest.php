<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Query;

use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\Application\Query\QueryLimits;
use ProfessionalWiki\NeoWiki\Application\Query\QueryRequest;
use ProfessionalWiki\NeoWiki\Application\Query\QueryResultNormalizer;
use ProfessionalWiki\NeoWiki\Application\Query\QueryService;
use ProfessionalWiki\NeoWiki\Persistence\Neo4j\QueryEngine;
use RuntimeException;
use Throwable;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Query\QueryService
 */
class QueryServiceTest extends TestCase {

	public function testReturnsRowsAsListWithColumnsFromFirstRow(): void {
		$service = $this->newService(
			$this->stubEngineWithRows( [
				[ 'name' => 'Ada', 'year' => 1815 ],
				[ 'name' => 'Grace', 'year' => 1906 ],
			] )
		);

		$result = $service->execute(
			$this->newRequest( 'MATCH (p:Person) RETURN p.name AS name, p.born AS year' )
		);

		$this->assertSame( [ 'name', 'year' ], $result->columns );
		$this->assertSame(
			[
				[ 'name' => 'Ada', 'year' => 1815 ],
				[ 'name' => 'Grace', 'year' => 1906 ],
			],
			$result->rows
		);
	}

	public function testEmptyResultProducesEmptyColumns(): void {
		$service = $this->newService( $this->stubEngineWithRows( [] ) );

		$result = $service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );

		$this->assertSame( [], $result->columns );
	}

	public function testEmptyResultProducesEmptyRows(): void {
		$service = $this->newService( $this->stubEngineWithRows( [] ) );

		$result = $service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );

		$this->assertSame( [], $result->rows );
	}

	public function testTruncatesAtMaxRowsAndSetsTruncatedFlag(): void {
		$service = $this->newService(
			$this->stubEngineWithRows( [
				[ 'i' => 1 ], [ 'i' => 2 ], [ 'i' => 3 ], [ 'i' => 4 ], [ 'i' => 5 ],
			] )
		);

		$result = $service->execute(
			$this->newRequest( 'MATCH (n) RETURN n.i AS i', limits: new QueryLimits( 30, 3 ) )
		);

		$this->assertCount( 3, $result->rows );
		$this->assertTrue( $result->truncated );
		$this->assertSame( 3, $result->resultCount );
	}

	public function testDoesNotSetTruncatedWhenResultExactlyEqualsMaxRows(): void {
		$service = $this->newService(
			$this->stubEngineWithRows( [
				[ 'i' => 1 ], [ 'i' => 2 ], [ 'i' => 3 ],
			] )
		);

		$result = $service->execute(
			$this->newRequest( 'MATCH (n) RETURN n.i AS i', limits: new QueryLimits( 30, 3 ) )
		);

		$this->assertFalse( $result->truncated );
		$this->assertSame( 3, $result->resultCount );
	}

	public function testReportsResultCountForUntruncatedResult(): void {
		$service = $this->newService(
			$this->stubEngineWithRows( [
				[ 'i' => 1 ], [ 'i' => 2 ],
			] )
		);

		$result = $service->execute( $this->newRequest( 'MATCH (n) RETURN n.i AS i' ) );

		$this->assertSame( 2, $result->resultCount );
	}

	public function testEmptyCypherThrowsEmptyQueryException(): void {
		$service = $this->newService( $this->stubEngineWithRows( [] ) );

		$this->expectException( EmptyQueryException::class );

		$service->execute( $this->newRequest( '   ' ) );
	}

	public function testRejectedQueryThrowsWriteQueryRejectedException(): void {
		$service = $this->newService(
			$this->stubEngineWithRows( [] ),
			validator: $this->fixedValidator( false )
		);

		$this->expectException( WriteQueryRejectedException::class );

		$service->execute( $this->newRequest( 'CREATE (n)' ) );
	}

	public function testTimeoutFromLimitsIsForwardedToEngine(): void {
		$capturedTimeout = null;
		$engine = $this->stubEngineCapturingTimeout( $capturedTimeout );

		$this->newService( $engine )->execute(
			$this->newRequest( 'MATCH (n) RETURN n', limits: new QueryLimits( 42, 1000 ) )
		);

		$this->assertSame( 42, $capturedTimeout );
	}

	public function testTimeoutErrorFromEngineIsTranslated(): void {
		$service = $this->newService(
			$this->stubEngineThrowing( $this->neo4jExceptionFor( 'Neo.ClientError.Transaction.TransactionTimedOut' ) )
		);

		$this->expectException( QueryTimeoutException::class );

		$service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );
	}

	public function testSyntaxErrorFromEngineIsTranslated(): void {
		$service = $this->newService(
			$this->stubEngineThrowing( $this->neo4jExceptionFor( 'Neo.ClientError.Statement.SyntaxError' ) )
		);

		$this->expectException( CypherSyntaxException::class );

		$service->execute( $this->newRequest( 'INVALID' ) );
	}

	public function testParameterMissingFromEngineIsTranslated(): void {
		$service = $this->newService(
			$this->stubEngineThrowing( $this->neo4jExceptionFor( 'Neo.ClientError.Statement.ParameterMissing' ) )
		);

		$this->expectException( ParameterMissingException::class );

		$service->execute( $this->newRequest( 'MATCH (n {id: $missing}) RETURN n' ) );
	}

	public function testUnknownNeo4jCodeBecomesInternalError(): void {
		$service = $this->newService(
			$this->stubEngineThrowing( $this->neo4jExceptionFor( 'Neo.DatabaseError.Statement.ExecutionFailed' ) )
		);

		$this->expectException( InternalQueryException::class );

		$service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );
	}

	public function testNonNeo4jExceptionBecomesBackendUnavailable(): void {
		$service = $this->newService(
			$this->stubEngineThrowing( new RuntimeException( 'connection refused' ) )
		);

		$this->expectException( BackendUnavailableException::class );

		$service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );
	}

	public function testValidatorExceptionBecomesBackendUnavailable(): void {
		$throwingValidator = new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				throw new RuntimeException( 'EXPLAIN failed: connection refused' );
			}
		};
		$service = $this->newService( $this->stubEngineWithRows( [] ), validator: $throwingValidator );

		$this->expectException( BackendUnavailableException::class );

		$service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );
	}

	public function testDurationMsIsNonNegativeInteger(): void {
		$service = $this->newService( $this->stubEngineWithRows( [ [ 'i' => 1 ] ] ) );

		$result = $service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );

		$this->assertGreaterThanOrEqual( 0, $result->durationMs );
	}

	public function testParametersAreForwardedToEngine(): void {
		$capturedParameters = null;
		$engine = new class( $capturedParameters ) implements QueryEngine {
			public function __construct( public mixed &$captured ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				$this->captured = $parameters;
				$summary = null;
				return new SummarizedResult( $summary, new CypherList( [] ) );
			}
		};

		$this->newService( $engine )->execute(
			new QueryRequest( 'MATCH (n {id: $x}) RETURN n', [ 'x' => 'subject-42' ], new QueryLimits( 30, 5000 ) )
		);

		$this->assertSame( [ 'x' => 'subject-42' ], $capturedParameters );
	}

	private function newService(
		QueryEngine $engine,
		?CypherQueryValidator $validator = null,
	): QueryService {
		return new QueryService(
			$engine,
			$validator ?? $this->fixedValidator( true ),
			new QueryResultNormalizer(),
		);
	}

	private function newRequest( string $cypher, array $parameters = [], ?QueryLimits $limits = null ): QueryRequest {
		return new QueryRequest( $cypher, $parameters, $limits ?? new QueryLimits( 30, 5000 ) );
	}

	private function fixedValidator( bool $allowed ): CypherQueryValidator {
		return new class( $allowed ) implements CypherQueryValidator {
			public function __construct( private readonly bool $allowed ) {
			}

			public function queryIsAllowed( string $cypher ): bool {
				return $this->allowed;
			}
		};
	}

	private function stubEngineWithRows( array $rows ): QueryEngine {
		$cypherMaps = array_map( fn( array $row ): CypherMap => new CypherMap( $row ), $rows );
		$summary = null;
		$result = new SummarizedResult( $summary, new CypherList( $cypherMaps ) );

		return new class( $result ) implements QueryEngine {
			public function __construct( private readonly SummarizedResult $result ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				return $this->result;
			}
		};
	}

	private function stubEngineCapturingTimeout( mixed &$capturedTimeout ): QueryEngine {
		return new class( $capturedTimeout ) implements QueryEngine {
			public function __construct( private mixed &$captured ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				$this->captured = $timeoutSeconds;
				$summary = null;
				return new SummarizedResult( $summary, new CypherList( [] ) );
			}
		};
	}

	private function stubEngineThrowing( Throwable $exception ): QueryEngine {
		return new class( $exception ) implements QueryEngine {
			public function __construct( private readonly Throwable $exception ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				throw $this->exception;
			}
		};
	}

	private function neo4jExceptionFor( string $code ): Neo4jException {
		return new Neo4jException( [
			Neo4jError::fromMessageAndCode( $code, 'test message' ),
		] );
	}

}
