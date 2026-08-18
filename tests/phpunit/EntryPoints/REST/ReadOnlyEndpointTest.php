<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionId;
use MediaWiki\Session\SessionProviderInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Every registered GET route must behave as a read endpoint. Driven off the route registrations so a
 * route added later cannot quietly opt out.
 *
 * Most tests use a non-persistent session: core overwrites the header for a persistent one, so the
 * cookieless anonymous case is the only one where NeoWiki's own value survives.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ReadOnlyEndpoint
 */
class ReadOnlyEndpointTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	/**
	 * @dataProvider readRouteProvider
	 */
	public function testReadResponsesAreNotStorableBySharedCaches( string $path, string $factory ): void {
		$cacheControl = $this->cacheControlOf( $factory, $this->anonymousSession() );

		$this->assertTrue(
			str_contains( $cacheControl, 'private' ) || str_contains( $cacheControl, 'no-store' ),
			"GET $path must keep its response out of shared caches: the body depends on the "
				. "requester's read rights. Cache-Control was '$cacheControl'."
		);
	}

	/**
	 * @dataProvider readRouteProvider
	 */
	public function testReadEndpointsDoNotDeclareWriteAccess( string $path, string $factory ): void {
		$this->assertFalse(
			$factory()->needsWriteAccess(),
			"GET $path declares write access, which makes CorsUtils reject anonymous cross-origin reads\n"
				. 'from unlisted origins.'
		);
	}

	public function testAnonymousAndLoggedInCallersGetTheSameValue(): void {
		$factory = $this->firstReadRouteFactory();

		$anonymous = $this->cacheControlOf( $factory, $this->anonymousSession() );

		$this->assertSame( 'private,must-revalidate,s-maxage=0', $anonymous );
		$this->assertSame( $anonymous, $this->cacheControlOf( $factory, $this->getSession( true ) ) );
	}

	private function cacheControlOf( string $factory, Session $session ): string {
		$handler = $factory();

		$this->initHandler( $handler, new RequestData( [ 'method' => 'GET' ] ), session: $session );

		$response = $handler->getResponseFactory()->create();
		$handler->applyCacheControl( $response );

		return $response->getHeaderLine( 'Cache-Control' );
	}

	private function firstReadRouteFactory(): string {
		foreach ( self::readRouteProvider() as [ $path, $factory ] ) {
			return $factory;
		}

		$this->fail( 'No GET routes are registered.' );
	}

	/**
	 * Forked from MediaWiki's SessionHelperTestTrait::getSession(), which hardcodes isPersistent()
	 * to true and so cannot express the anonymous case.
	 */
	private function anonymousSession(): Session {
		$provider = $this->createMock( SessionProviderInterface::class );
		$provider->method( 'safeAgainstCsrf' )->willReturn( true );

		$session = $this->createMock( Session::class );
		$session->method( 'getSessionId' )->willReturn( new SessionId( 'test' ) );
		$session->method( 'getProvider' )->willReturn( $provider );
		$session->method( 'isPersistent' )->willReturn( false );

		return $session;
	}

	/**
	 * @return iterable<string, array{string, string}> route path and handler factory, per GET route.
	 */
	public static function readRouteProvider(): iterable {
		foreach ( self::registeredRoutes() as $route ) {
			// Mirrors ExtraRoutesModule: an absent method means GET, and a bare string is one method.
			$methods = isset( $route['method'] ) ? (array)$route['method'] : [ 'GET' ];

			if ( in_array( 'GET', $methods, true ) ) {
				yield $route['path'] => [ $route['path'], '\\' . $route['factory'] ];
			}
		}
	}

	/**
	 * Routes come from two places: extension.json, and the per-plugin files the graph plugins add to
	 * $wgRestAPIAdditionalRouteFiles when their store is configured.
	 *
	 * @return iterable<array<string, mixed>>
	 */
	private static function registeredRoutes(): iterable {
		$extensionRoot = __DIR__ . '/../../../../';

		yield from self::readJson( $extensionRoot . 'extension.json' )['RestRoutes'];

		foreach ( glob( $extensionRoot . 'src/GraphDatabasePlugins/*/EntryPoints/REST/*Routes.json' ) as $file ) {
			yield from self::readJson( $file );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function readJson( string $path ): array {
		return json_decode( (string)file_get_contents( $path ), true );
	}

}
