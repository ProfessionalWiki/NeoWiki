<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\Application\Query\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\Application\Query\QueryRequest;
use ProfessionalWiki\NeoWiki\Application\Query\QueryResult;
use ProfessionalWiki\NeoWiki\Application\Query\QueryService;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\QueryCypherApi;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\QueryCypherApi
 * @group Database
 */
class QueryCypherApiTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testReturns200WithEnvelopeOnSuccess(): void {
		$service = $this->stubServiceReturning( new QueryResult(
			columns: [ 'name' ],
			rows: [ [ 'name' => 'Ada' ] ],
			truncated: false,
			resultCount: 1,
			durationMs: 12,
		) );

		$response = $this->executeRequest( $service, [ 'cypher' => 'MATCH (n) RETURN n.name AS name' ] );
		$body = $this->decodeBody( $response );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [ 'name' ], $body['columns'] );
		$this->assertSame( [ [ 'name' => 'Ada' ] ], $body['rows'] );
		$this->assertFalse( $body['truncated'] );
		$this->assertSame( 1, $body['resultCount'] );
		$this->assertSame( 12, $body['durationMs'] );
	}

	public function testEmptyQueryExceptionMapsTo400EmptyQuery(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new EmptyQueryException( 'empty' ) ),
			[ 'cypher' => '   ' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'emptyQuery', $body['errorType'] );
	}

	public function testWriteQueryExceptionMapsTo422WriteQueryRejected(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new WriteQueryRejectedException( 'write' ) ),
			[ 'cypher' => 'CREATE (n)' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 422, $response->getStatusCode() );
		$this->assertSame( 'writeQueryRejected', $body['errorType'] );
	}

	public function testTimeoutExceptionMapsTo408QueryTimeout(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new QueryTimeoutException( 'tmo' ) ),
			[ 'cypher' => 'MATCH (n) RETURN n' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 408, $response->getStatusCode() );
		$this->assertSame( 'queryTimeout', $body['errorType'] );
	}

	public function testSyntaxExceptionMapsTo400CypherSyntaxError(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new CypherSyntaxException( 'syntax' ) ),
			[ 'cypher' => 'INVALID' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'cypherSyntaxError', $body['errorType'] );
	}

	public function testParameterMissingExceptionMapsTo400ParameterMissing(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new ParameterMissingException( 'missing' ) ),
			[ 'cypher' => 'MATCH (n {id: $missing}) RETURN n' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'parameterMissing', $body['errorType'] );
	}

	public function testBackendUnavailableExceptionMapsTo503(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new BackendUnavailableException( 'backend' ) ),
			[ 'cypher' => 'MATCH (n) RETURN n' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 503, $response->getStatusCode() );
		$this->assertSame( 'backendUnavailable', $body['errorType'] );
	}

	public function testInternalQueryExceptionMapsTo500InternalError(): void {
		$response = $this->executeRequest(
			$this->stubServiceThrowing( new InternalQueryException( 'internal' ) ),
			[ 'cypher' => 'MATCH (n) RETURN n' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 500, $response->getStatusCode() );
		$this->assertSame( 'internalError', $body['errorType'] );
	}

	public function testReturns403WhenUserLacksNeowikiQueryRight(): void {
		$response = $this->executeRequest(
			$this->stubServiceReturning( $this->emptyResult() ),
			[ 'cypher' => 'MATCH (n) RETURN n' ],
			authority: $this->mockAnonAuthorityWithPermissions( [] )
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'permissionDenied', $body['errorType'] );
	}

	public function testRateLimitedReturns429(): void {
		// Rate-limit triggering requires PingLimiter setup that is too entangled
		// with the MW session/request infrastructure to test in unit/integration style.
		// Covered by manual testing or future end-to-end integration coverage.
		$this->markTestIncomplete( 'Rate-limit gate is tested manually; PingLimiter is not easily triggered in integration tests.' );
	}

	private function executeRequest(
		QueryService $service,
		array $body,
		?Authority $authority = null
	): \MediaWiki\Rest\ResponseInterface {
		return $this->executeHandler(
			new QueryCypherApi( $service ),
			new RequestData( [
				'method' => 'POST',
				'bodyContents' => json_encode( $body ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] ),
			authority: $authority
		);
	}

	private function decodeBody( \MediaWiki\Rest\ResponseInterface $response ): array {
		return json_decode( $response->getBody()->getContents(), true );
	}

	private function stubServiceReturning( QueryResult $result ): QueryService {
		return new readonly class( $result ) extends QueryService {
			public function __construct( private readonly QueryResult $result ) {
				// Intentionally skip parent constructor — dependencies not needed for stubs.
			}

			public function execute( QueryRequest $request ): QueryResult {
				return $this->result;
			}
		};
	}

	private function stubServiceThrowing( \Throwable $exception ): QueryService {
		return new readonly class( $exception ) extends QueryService {
			public function __construct( private readonly \Throwable $exception ) {
				// Intentionally skip parent constructor — dependencies not needed for stubs.
			}

			public function execute( QueryRequest $request ): QueryResult {
				throw $this->exception;
			}
		};
	}

	private function emptyResult(): QueryResult {
		return new QueryResult( [], [], false, 0, 0 );
	}

}
