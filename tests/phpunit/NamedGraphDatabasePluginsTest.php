<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Neo4jPlugin;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * Every configured graph database backend is addressable by name, which is what scopes a rebuild to
 * one store.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class NamedGraphDatabasePluginsTest extends NeoWikiIntegrationTestCase {

	protected function tearDown(): void {
		parent::tearDown();
		// The tests rebuild the singleton from overridden config and temporary hooks; reset it so later
		// tests get an instance built from the real ones.
		NeoWikiExtension::resetInstance();
	}

	public function testNeo4jIsNamedAfterItsBackend(): void {
		$this->assertArrayHasKey(
			Neo4jPlugin::STORE_NAME,
			NeoWikiExtension::getInstance()->getNamedGraphDatabasePlugins()
		);
	}

	public function testEachConfiguredStoreIsAddressableByName(): void {
		$this->configureSparqlStores( [
			[ 'updateUrl' => 'https://qlever.example/api' ],
			[ 'updateUrl' => 'https://qlever.example/api', 'projection' => 'EDM' ],
			[ 'updateUrl' => 'https://other.example/api', 'projection' => 'EDM', 'name' => 'edm-archive' ],
		] );
		$this->registerNamedGraphDatabasePlugins( [ 'redherb' => new SpyGraphDatabasePlugin() ] );

		$this->assertSame(
			[ 'neo4j', 'native', 'EDM', 'edm-archive', 'redherb' ],
			array_keys( NeoWikiExtension::getInstance()->getNamedGraphDatabasePlugins() ),
			'the bundled backends come first, in configuration order, then the extension plugins'
		);
	}

	public function testEachNameResolvesToItsOwnBackend(): void {
		$spy = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'redherb' => $spy ] );

		$plugins = NeoWikiExtension::getInstance()->getNamedGraphDatabasePlugins();

		$this->assertSame( $spy, $plugins['redherb'] );
		$this->assertNotSame( $spy, $plugins[Neo4jPlugin::STORE_NAME] );
	}

	public function testAnExtensionCannotShadowABundledBackend(): void {
		$spy = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ Neo4jPlugin::STORE_NAME => $spy ] );

		$plugins = NeoWikiExtension::getInstance()->getNamedGraphDatabasePlugins();

		$this->assertNotSame( $spy, $plugins[Neo4jPlugin::STORE_NAME] );
	}

	/**
	 * @param array<int, array<string, string>> $stores
	 */
	private function configureSparqlStores( array $stores ): void {
		$this->overrideConfigValue( 'NeoWikiSparqlStores', $stores );
		NeoWikiExtension::resetInstance();
	}

}
