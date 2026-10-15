<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\GraphDatabasePlugins\Neo4j\EntryPoints\REST;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\Neo4jRouteRegistration;

/**
 * @covers \ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\EntryPoints\REST\Neo4jRouteRegistration
 */
class Neo4jRouteRegistrationTest extends TestCase {

	public function testReturnsRouteFileWhenConfigured(): void {
		$files = Neo4jRouteRegistration::routeFiles( 'bolt://read', 'bolt://write' );

		$this->assertCount( 1, $files );
		$this->assertStringEndsWith( 'neo4jRoutes.json', $files[0] );
		$this->assertFileExists( $files[0] );
	}

	public function testReturnsNothingWhenUnconfigured(): void {
		$this->assertSame( [], Neo4jRouteRegistration::routeFiles( null, null ) );
	}

	public function testReturnsNothingWhenOnlyOneUrlSet(): void {
		$this->assertSame( [], Neo4jRouteRegistration::routeFiles( 'bolt://read', null ) );
		$this->assertSame( [], Neo4jRouteRegistration::routeFiles( null, 'bolt://write' ) );
	}

	public function testRouteFileDeclaresCypherRoute(): void {
		$routes = json_decode( file_get_contents( Neo4jRouteRegistration::routeFiles( 'r', 'w' )[0] ), true );

		$this->assertSame( '/neowiki/v0/query/cypher', $routes[0]['path'] );
		$this->assertSame( [ 'POST' ], $routes[0]['method'] );
		$this->assertSame(
			'ProfessionalWiki\\NeoWiki\\NeoWikiExtension::newCypherQueryApi',
			$routes[0]['factory']
		);
	}

}
