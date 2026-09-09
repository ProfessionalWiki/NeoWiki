<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\Application;

use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\RawQueryAuthorizer;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\CypherQueryValidator;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryPermissionDeniedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryLimits;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jResultNormalizer;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jReadQueryEngine;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubRawQueryAuthorizer;
use RuntimeException;
use Throwable;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService
 */
class Neo4jQueryServiceTest extends TestCase {

	/**
	 * How the driver reports an unreachable server: by quoting the Bolt URI it dialed, credentials
	 * included. The query surfaces hand this message to callers, anonymous ones included.
	 */
	private const string UNREACHABLE_SERVER_MESSAGE =
		"Cannot connect to any server on alias: default with Uris: ('bolt://neowiki_read:S3cr3t@graph.example:7687')";

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

	public function testEngineBackendFailureDoesNotCarryCredentials(): void {
		$service = $this->newService(
			$this->stubEngineThrowing( new RuntimeException( self::UNREACHABLE_SERVER_MESSAGE ) )
		);

		$message = $this->executeAndCatch( $service )->getMessage();

		$this->assertStringNotContainsString( 'S3cr3t', $message );
		$this->assertStringContainsString( 'graph.example', $message );
	}

	public function testValidatorBackendFailureDoesNotCarryCredentials(): void {
		$throwingValidator = new class( self::UNREACHABLE_SERVER_MESSAGE ) implements CypherQueryValidator {
			public function __construct( private string $message ) {
			}

			public function queryIsAllowed( string $cypher ): bool {
				throw new RuntimeException( $this->message );
			}
		};
		$service = $this->newService( $this->stubEngineWithRows( [] ), validator: $throwingValidator );

		$message = $this->executeAndCatch( $service )->getMessage();

		$this->assertStringNotContainsString( 'S3cr3t', $message );
		$this->assertStringContainsString( 'graph.example', $message );
	}

	private function executeAndCatch( Neo4jQueryService $service ): BackendUnavailableException {
		try {
			$service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );
		} catch ( BackendUnavailableException $e ) {
			return $e;
		}

		$this->fail( 'Expected a BackendUnavailableException.' );
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

	public function testDeniedRawQueryIsRejectedBeforeTheStoreIsAsked(): void {
		$validatorReachingTheStore = new class implements CypherQueryValidator {
			public function queryIsAllowed( string $cypher ): bool {
				throw new RuntimeException( 'EXPLAIN reached the store' );
			}
		};
		$service = $this->newService(
			$this->stubEngineThrowing( new RuntimeException( 'the query reached the store' ) ),
			validator: $validatorReachingTheStore,
			authorizer: new StubRawQueryAuthorizer( false )
		);

		$this->expectException( QueryPermissionDeniedException::class );

		$service->execute( $this->newRequest( 'MATCH (n) RETURN n' ) );
	}

	private function newService(
		Neo4jReadQueryEngine $engine,
		?CypherQueryValidator $validator = null,
		?RawQueryAuthorizer $authorizer = null,
	): Neo4jQueryService {
		return new Neo4jQueryService(
			$engine,
			$validator ?? $this->fixedValidator( true ),
			new Neo4jResultNormalizer(),
			$authorizer ?? new StubRawQueryAuthorizer( true ),
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
