<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Sparql\Persistence;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Application\Exception\SparqlUpdateFailedException;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlUpdateEndpoint;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Sparql\Persistence\HttpSparqlUpdateEndpoint
 */
class HttpSparqlUpdateEndpointTest extends TestCase {

	private const string URL = 'https://qlever.example/api/neowiki';

	/**
	 * @var array<string, string>
	 */
	private array $sentHeaders = [];

	private ?string $requestedUrl = null;

	/**
	 * @var array<string, mixed>
	 */
	private array $requestOptions = [];

	public function testPostsUpdateAsSparqlUpdateBodyWithBearerToken(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 200, '{}' ) );

		( new HttpSparqlUpdateEndpoint( $factory, self::URL, 'secret-token' ) )
			->postUpdate( 'DROP SILENT GRAPH <https://wiki.example/page/1>' );

		$this->assertSame( self::URL, $this->requestedUrl );
		$this->assertSame( 'POST', $this->requestOptions['method'] );
		$this->assertSame( 'DROP SILENT GRAPH <https://wiki.example/page/1>', $this->requestOptions['postData'] );
		$this->assertSame( 'application/sparql-update', $this->sentHeaders['Content-Type'] );
		$this->assertSame( 'Bearer secret-token', $this->sentHeaders['Authorization'] );
	}

	public function testOmitsAuthorizationHeaderWhenNoAccessTokenIsConfigured(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 200, '{}' ) );

		( new HttpSparqlUpdateEndpoint( $factory, self::URL, null ) )->postUpdate( 'DROP SILENT GRAPH <g>' );

		$this->assertArrayNotHasKey( 'Authorization', $this->sentHeaders );
	}

	public function testNonSuccessResponseThrowsWithUrlStatusAndResponseBody(): void {
		$factory = $this->httpRequestFactoryReturning( $this->fakeRequest( 500, 'permission denied' ) );

		try {
			( new HttpSparqlUpdateEndpoint( $factory, self::URL, 'secret-token' ) )->postUpdate( 'DROP SILENT GRAPH <g>' );
			$this->fail( 'Expected SparqlUpdateFailedException' );
		} catch ( SparqlUpdateFailedException $exception ) {
			$this->assertStringContainsString( self::URL, $exception->getMessage() );
			$this->assertStringContainsString( '500', $exception->getMessage() );
			$this->assertStringContainsString( 'permission denied', $exception->getMessage() );
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
