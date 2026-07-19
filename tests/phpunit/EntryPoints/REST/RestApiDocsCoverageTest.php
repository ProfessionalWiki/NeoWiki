<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use PHPUnit\Framework\TestCase;

/**
 * Guards the hand-written endpoint table in docs/api/rest-api.md against the routes actually
 * registered in extension.json. That table is the install-free, linkable REST reference, so it must
 * list every registered route and no stale ones.
 *
 * Complements ModuleSpecHandlerNeoWikiTest, which checks the generated OpenAPI spec against the
 * handlers. This test only compares the documented route list with extension.json: pure file I/O,
 * no database or framework bootstrap needed.
 *
 * When this fails, edit the Endpoints tables in docs/api/rest-api.md (between the
 * REST-ENDPOINTS markers) so they match the routes in extension.json.
 *
 * @coversNothing
 */
class RestApiDocsCoverageTest extends TestCase {

	private const REGION_START = '<!-- REST-ENDPOINTS:START';
	private const REGION_END = '<!-- REST-ENDPOINTS:END';

	public function testEveryRegisteredRouteIsDocumented(): void {
		$undocumented = array_values(
			array_diff( $this->registeredEndpoints(), $this->documentedEndpoints() )
		);

		$this->assertSame(
			[],
			$undocumented,
			'These routes are registered in extension.json but are missing from the endpoint table in '
				. 'docs/api/rest-api.md. Add a row for each.'
		);
	}

	public function testNoStaleEndpointsAreDocumented(): void {
		$stale = array_values(
			array_diff( $this->documentedEndpoints(), $this->registeredEndpoints() )
		);

		$this->assertSame(
			[],
			$stale,
			'These endpoints are listed in the docs/api/rest-api.md table but are not registered '
				. 'in extension.json. Remove the stale rows.'
		);
	}

	public function testEndpointTableIsNotEmpty(): void {
		$this->assertNotEmpty(
			$this->documentedEndpoints(),
			'No endpoints were parsed from the REST-ENDPOINTS region of docs/api/rest-api.md. '
				. 'The table or its markers may be malformed.'
		);
	}

	/**
	 * @return list<string> e.g. "GET /neowiki/v0/subject/{subjectId}", sorted and unique.
	 */
	private function registeredEndpoints(): array {
		$extensionJson = $this->readJsonArray( __DIR__ . '/../../../../extension.json' );
		$this->assertArrayHasKey( 'RestRoutes', $extensionJson );

		// The Cypher route is registered per-plugin via a route file added to
		// $wgRestAPIAdditionalRouteFiles only when Neo4j is configured, not via extension.json.
		// It is still a real, documented endpoint, so count it as registered here too.
		$pluginRouteFile = __DIR__
			. '/../../../../src/GraphDatabasePlugins/Neo4j/EntryPoints/REST/neo4jRoutes.json';
		$routes = array_merge( $extensionJson['RestRoutes'], $this->readJsonArray( $pluginRouteFile ) );

		$endpoints = [];
		foreach ( $routes as $route ) {
			$methods = is_array( $route['method'] ) ? $route['method'] : [ $route['method'] ];
			foreach ( $methods as $method ) {
				$endpoints[] = strtoupper( $method ) . ' ' . $route['path'];
			}
		}

		return $this->normalise( $endpoints );
	}

	/**
	 * @return array<mixed>
	 */
	private function readJsonArray( string $path ): array {
		$contents = file_get_contents( $path );
		$this->assertNotFalse( $contents, "Could not read $path" );

		$decoded = json_decode( $contents, true );
		$this->assertIsArray( $decoded );

		return $decoded;
	}

	/**
	 * @return list<string> the "METHOD /neowiki/v0/..." spans inside the marked table region.
	 */
	private function documentedEndpoints(): array {
		$contents = file_get_contents( __DIR__ . '/../../../../docs/api/rest-api.md' );
		$this->assertNotFalse( $contents, 'Could not read docs/api/rest-api.md' );

		$start = strpos( $contents, self::REGION_START );
		$end = strpos( $contents, self::REGION_END );
		$this->assertNotFalse( $start, 'REST-ENDPOINTS:START marker missing from rest-api.md' );
		$this->assertNotFalse( $end, 'REST-ENDPOINTS:END marker missing from rest-api.md' );
		$this->assertLessThan( $end, $start, 'REST-ENDPOINTS markers are out of order' );

		$region = substr( $contents, $start, $end - $start );

		preg_match_all(
			'#`(GET|POST|PUT|PATCH|DELETE) (/neowiki/v0/[^\s`]+)`#',
			$region,
			$matches,
			PREG_SET_ORDER
		);

		$endpoints = [];
		foreach ( $matches as $match ) {
			$endpoints[] = $match[1] . ' ' . $match[2];
		}

		return $this->normalise( $endpoints );
	}

	/**
	 * @param list<string> $endpoints
	 * @return list<string>
	 */
	private function normalise( array $endpoints ): array {
		$endpoints = array_values( array_unique( $endpoints ) );
		sort( $endpoints );
		return $endpoints;
	}

}
