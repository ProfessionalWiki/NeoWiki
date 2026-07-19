<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Application;

use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jReadQueryEngine;
use RuntimeException;
use Throwable;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService
 */
class Neo4jQueryServiceTest extends TestCase {

	public function testReturnsRowsAsListWithColumnsFromProtocolKeys(): void {
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

	public function testColumnsComeFromProtocolKeysEvenWhenResultIsEmpty(): void {
		$service = $this->newService(
			$this->stubEngineWithRows( rows: [], keys: [ 'name', 'year' ] )
		);

		$result = $service->execute( $this->newRequest( 'MATCH (p:Person) WHERE false RETURN p.name AS name, p.born AS year' ) );

		$this->assertSame( [ 'name', 'year' ], $result->columns );
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
			$this->newRequest( 'MATCH (n) RETURN n.i AS i', limits: new Neo4jQueryLimits( 30, 3 ) )
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
			$this->newRequest( 'MATCH (n) RETURN n.i AS i', limits: new Neo4jQueryLimits( 30, 3 ) )
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
			$this->newRequest( 'MATCH (n) RETURN n', limits: new Neo4jQueryLimits( 42, 1000 ) )
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

	public function testValidatorNeo4jSyntaxErrorBecomesCypherSyntaxException(): void {
		$throwingValidator = new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				throw new Neo4jException( [
					Neo4jError::fromMessageAndCode( 'Neo.ClientError.Statement.SyntaxError', 'bad syntax' ),
				] );
			}
		};
		$service = $this->newService( $this->stubEngineWithRows( [] ), validator: $throwingValidator );

		$this->expectException( CypherSyntaxException::class );

		$service->execute( $this->newRequest( 'INVALID' ) );
	}

	public function testDurationMsIsNonNegativeInteger(): void {
		$service = $this->newService( $this->stubEngineWithRows( [ [ 'i' => 1 ] ] ) );

		$result = $service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );

		$this->assertGreaterThanOrEqual( 0, $result->durationMs );
	}

	public function testParametersAreForwardedToEngine(): void {
		$capturedParameters = null;
		$engine = new class( $capturedParameters ) implements Neo4jReadQueryEngine {
			public function __construct( public mixed &$captured ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				$this->captured = $parameters;
				$summary = null;
				return new SummarizedResult( $summary, new CypherList( [] ) );
			}
		};

		$this->newService( $engine )->execute(
			new Neo4jQueryRequest( 'MATCH (n {id: $x}) RETURN n', [ 'x' => 'subject-42' ], new Neo4jQueryLimits( 30, 5000 ) )
		);

		$this->assertSame( [ 'x' => 'subject-42' ], $capturedParameters );
	}

	private function newService(
		Neo4jReadQueryEngine $engine,
		?CypherQueryValidator $validator = null,
	): Neo4jQueryService {
		return new Neo4jQueryService(
			$engine,
			$validator ?? $this->fixedValidator( true ),
			new Neo4jResultNormalizer(),
		);
	}

	private function newRequest( string $cypher, array $parameters = [], ?Neo4jQueryLimits $limits = null ): Neo4jQueryRequest {
		return new Neo4jQueryRequest( $cypher, $parameters, $limits ?? new Neo4jQueryLimits( 30, 5000 ) );
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

	/**
	 * @param array<int, array<string,mixed>> $rows
	 * @param list<string> $keys Column names to expose via SummarizedResult::keys(); defaults to the keys of the first row.
	 */
	private function stubEngineWithRows( array $rows, array $keys = [] ): Neo4jReadQueryEngine {
		$cypherMaps = array_map( fn( array $row ): CypherMap => new CypherMap( $row ), $rows );
		$summary = null;
		if ( $keys === [] && $rows !== [] ) {
			$keys = array_keys( $rows[0] );
		}
		$result = new SummarizedResult( $summary, new CypherList( $cypherMaps ), $keys );

		return new class( $result ) implements Neo4jReadQueryEngine {
			public function __construct( private readonly SummarizedResult $result ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				return $this->result;
			}
		};
	}

	private function stubEngineCapturingTimeout( mixed &$capturedTimeout ): Neo4jReadQueryEngine {
		return new class( $capturedTimeout ) implements Neo4jReadQueryEngine {
			public function __construct( private mixed &$captured ) {
			}

			public function runReadQuery( string $cypher, array $parameters = [], ?int $timeoutSeconds = null ): SummarizedResult {
				$this->captured = $timeoutSeconds;
				$summary = null;
				return new SummarizedResult( $summary, new CypherList( [] ) );
			}
		};
	}

	private function stubEngineThrowing( Throwable $exception ): Neo4jReadQueryEngine {
		return new class( $exception ) implements Neo4jReadQueryEngine {
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
