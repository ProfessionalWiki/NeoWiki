<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\EntryPoints\REST;

use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\REST\SparqlQueryApi;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FakeSparqlQueryEndpoint;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\EntryPoints\REST\SparqlQueryApi
 * @group Database
 */
class SparqlQueryApiTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;

	private const string RESULTS = '{"head":{"vars":["label"]},"results":{"bindings":[{"label":{"type":"literal","value":"Bach"}}]}}';

	public function testReturns200WithUnmodifiedResultsDocument(): void {
		$response = $this->executeRequest(
			$this->serviceReturning( self::RESULTS ),
			[ 'query' => 'SELECT ?label WHERE { ?s ?p ?label }' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( [ 'label' ], $body['head']['vars'] );
		$this->assertSame( 'Bach', $body['results']['bindings'][0]['label']['value'] );
	}

	public function testEmptyQueryMapsTo400EmptyQuery(): void {
		$response = $this->executeRequest( $this->serviceReturning( self::RESULTS ), [ 'query' => '   ' ] );
		$body = $this->decodeBody( $response );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'emptyQuery', $body['errorType'] );
	}

	public function testStoreRejectionMapsTo400SparqlSyntaxError(): void {
		$response = $this->executeRequest(
			$this->serviceFailingWith( new SparqlQueryFailedException( 'https://s.example', 400, 'parse error' ) ),
			[ 'query' => 'INVALID' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertSame( 'sparqlSyntaxError', $body['errorType'] );
	}

	public function testStoreFailureMapsTo503StoreUnavailable(): void {
		$response = $this->executeRequest(
			$this->serviceFailingWith( new SparqlQueryFailedException( 'https://s.example', 0, '' ) ),
			[ 'query' => 'SELECT * WHERE { ?s ?p ?o }' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 503, $response->getStatusCode() );
		$this->assertSame( 'sparqlStoreUnavailable', $body['errorType'] );
	}

	public function testNonJsonResponseMapsTo500InternalError(): void {
		$response = $this->executeRequest(
			$this->serviceReturning( '<html>Bad Gateway</html>' ),
			[ 'query' => 'SELECT * WHERE { ?s ?p ?o }' ]
		);
		$body = $this->decodeBody( $response );

		$this->assertSame( 500, $response->getStatusCode() );
		$this->assertSame( 'internalError', $body['errorType'] );
	}

	public function testReturns403WhenUserLacksNeowikiQueryRight(): void {
		$response = $this->executeRequest(
			$this->serviceReturning( self::RESULTS ),
			[ 'query' => 'SELECT * WHERE { ?s ?p ?o }' ],
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
			$this->serviceReturning( self::RESULTS ),
			[ 'query' => 'SELECT * WHERE { ?s ?p ?o }' ],
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
		SparqlQueryService $service,
		array $body,
		?Authority $authority = null
	): ResponseInterface {
		return $this->executeHandler(
			new SparqlQueryApi( $service ),
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

	private function serviceReturning( string $responseBody ): SparqlQueryService {
		return new SparqlQueryService( FakeSparqlQueryEndpoint::returning( $responseBody ) );
	}

	private function serviceFailingWith( SparqlQueryFailedException $failure ): SparqlQueryService {
		return new SparqlQueryService( FakeSparqlQueryEndpoint::failingWith( $failure ) );
	}

}
