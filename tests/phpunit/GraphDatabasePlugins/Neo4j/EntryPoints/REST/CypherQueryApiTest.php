<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\EntryPoints\REST;

use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Throwable;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryRequest;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryResult;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Application\Neo4jQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\CypherQueryApi;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\CypherQueryApi
 * @group Database
 */
class CypherQueryApiTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	public function testReturns200WithEnvelopeOnSuccess(): void {
		$service = $this->stubServiceReturning( new Neo4jQueryResult(
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
		$this->overrideConfigValue(
			MainConfigNames::RateLimits,
			[ 'neowiki-query' => [ 'user' => [ 0, 60 ] ] ]
		);

		$response = $this->executeRequest(
			$this->stubServiceReturning( $this->emptyResult() ),
			[ 'cypher' => 'MATCH (n) RETURN n' ],
			authority: $this->mockUserAuthorityWithPermissions(
				$this->getTestUser()->getUser(),
				[ 'neowiki-query' ]
			)
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 429, $response->getStatusCode() );
		$this->assertSame( 'rateLimitExceeded', $body['errorType'] );
	}

	private function executeRequest(
		Neo4jQueryService $service,
		array $body,
		?Authority $authority = null
	): ResponseInterface {
		return $this->executeHandler(
			new CypherQueryApi( $service ),
			new RequestData( [
				'method' => 'POST',
				'bodyContents' => json_encode( $body ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] ),
			authority: $authority
		);
	}

	private function decodeBody( ResponseInterface $response ): array {
		return json_decode( $response->getBody()->getContents(), true );
	}

	private function stubServiceReturning( Neo4jQueryResult $result ): Neo4jQueryService {
		return new readonly class( $result ) extends Neo4jQueryService {
			public function __construct( private readonly Neo4jQueryResult $result ) {
				// Intentionally skip parent constructor — dependencies not needed for stubs.
			}

			public function execute( Neo4jQueryRequest $request ): Neo4jQueryResult {
				return $this->result;
			}
		};
	}

	private function stubServiceThrowing( Throwable $exception ): Neo4jQueryService {
		return new readonly class( $exception ) extends Neo4jQueryService {
			public function __construct( private readonly Throwable $exception ) {
				// Intentionally skip parent constructor — dependencies not needed for stubs.
			}

			public function execute( Neo4jQueryRequest $request ): Neo4jQueryResult {
				throw $this->exception;
			}
		};
	}

	private function emptyResult(): Neo4jQueryResult {
		return new Neo4jQueryResult( [], [], false, 0, 0 );
	}

}
