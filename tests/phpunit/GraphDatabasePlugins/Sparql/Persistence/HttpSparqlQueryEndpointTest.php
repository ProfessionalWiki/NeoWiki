<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Persistence;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlQueryEndpoint
 */
class HttpSparqlQueryEndpointTest extends TestCase {

	private const string URL = 'https://qlever.example/api/neowiki';
	private const string RESULTS = '{"head":{"vars":["s"]},"results":{"bindings":[]}}';

	/**
	 * @var array<string, string>
	 */
	private array $sentHeaders = [];

	private ?string $requestedUrl = null;

	/**
	 * @var array<string, mixed>
	 */
	private array $requestOptions = [];

	public function testPostsQueryAsSparqlQueryBodyWithHeadersAndTimeout(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 200, self::RESULTS ) );

		$body = ( new HttpSparqlQueryEndpoint( $factory, self::URL, 'secret-token' ) )
			->runQuery( 'SELECT ?s WHERE { ?s ?p ?o }', 42 );

		$this->assertSame( self::RESULTS, $body );
		$this->assertSame( self::URL, $this->requestedUrl );
		$this->assertSame( 'POST', $this->requestOptions['method'] );
		$this->assertSame( 'SELECT ?s WHERE { ?s ?p ?o }', $this->requestOptions['postData'] );
		$this->assertSame( 42, $this->requestOptions['timeout'] );
		$this->assertSame( 'application/sparql-query', $this->sentHeaders['Content-Type'] );
		$this->assertSame( 'application/sparql-results+json', $this->sentHeaders['Accept'] );
		$this->assertSame( 'Bearer secret-token', $this->sentHeaders['Authorization'] );
	}

	public function testOmitsAuthorizationHeaderWhenNoAccessTokenIsConfigured(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 200, self::RESULTS ) );

		( new HttpSparqlQueryEndpoint( $factory, self::URL, null ) )->runQuery( 'SELECT * WHERE { ?s ?p ?o }', 30 );

		$this->assertArrayNotHasKey( 'Authorization', $this->sentHeaders );
	}

	public function testNonSuccessResponseThrowsWithUrlStatusAndResponseBody(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 400, 'parse error at line 1' ) );

		try {
			( new HttpSparqlQueryEndpoint( $factory, self::URL, 'secret-token' ) )->runQuery( 'INVALID', 30 );
			$this->fail( 'Expected SparqlQueryFailedException' );
		} catch ( SparqlQueryFailedException $exception ) {
			$this->assertSame( 400, $exception->httpStatus );
			$this->assertStringContainsString( self::URL, $exception->getMessage() );
			$this->assertStringContainsString( 'parse error at line 1', $exception->getMessage() );
		}
	}

	public function testTransportFailureSurfacesAsStatusZero(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 0, '' ) );

		try {
			( new HttpSparqlQueryEndpoint( $factory, self::URL, null ) )->runQuery( 'SELECT * WHERE { ?s ?p ?o }', 30 );
			$this->fail( 'Expected SparqlQueryFailedException' );
		} catch ( SparqlQueryFailedException $exception ) {
			$this->assertSame( 0, $exception->httpStatus );
		}
	}

	public function testRedirectResponseIsTreatedAsFailureNotSuccess(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 302, 'Found' ) );

		try {
			( new HttpSparqlQueryEndpoint( $factory, self::URL, null ) )->runQuery( 'SELECT * WHERE { ?s ?p ?o }', 30 );
			$this->fail( 'Expected SparqlQueryFailedException' );
		} catch ( SparqlQueryFailedException $exception ) {
			$this->assertSame( 302, $exception->httpStatus );
		}
	}

	private function httpRequestFactoryReturning( MWHttpRequest $request ): HttpRequestFactory {
		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )->willReturnCallback(
			function ( $url, $options = [] ) use ( $request ): MWHttpRequest {
				$this->requestedUrl = $url;
				$this->requestOptions = $options;
				return $request;
			}
		);

		return $factory;
	}

	private function fakeRequest( int $status, string $body ): MWHttpRequest {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'setHeader' )->willReturnCallback(
			function ( string $name, $value ): void {
				$this->sentHeaders[$name] = $value;
			}
		);
		$request->method( 'getStatus' )->willReturn( $status );
		$request->method( 'getContent' )->willReturn( $body );

		return $request;
	}

}
