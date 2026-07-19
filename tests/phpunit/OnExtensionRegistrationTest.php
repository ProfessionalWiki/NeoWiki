<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::onExtensionRegistration
 */
class OnExtensionRegistrationTest extends TestCase {

	use HandlesNeo4jEnvOverrides;

	private mixed $routeFiles;
	private mixed $writeUrl;
	private mixed $readUrl;
	private mixed $sparqlStores;

	protected function setUp(): void {
		parent::setUp();
		$this->routeFiles = $GLOBALS['wgRestAPIAdditionalRouteFiles'] ?? null;
		$this->writeUrl = $GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] ?? null;
		$this->readUrl = $GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] ?? null;
		$this->sparqlStores = $GLOBALS['wgNeoWikiSparqlStores'] ?? null;

		// Clear the CI env overrides so the config-value path is exercised deterministically.
		$this->snapshotAndClearNeo4jEnvOverrides();
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = [];
		$GLOBALS['wgNeoWikiSparqlStores'] = null;
	}

	protected function tearDown(): void {
		$this->restoreNeo4jEnvOverrides();
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = $this->routeFiles;
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = $this->writeUrl;
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = $this->readUrl;
		$GLOBALS['wgNeoWikiSparqlStores'] = $this->sparqlStores;
		parent::tearDown();
	}

	public function testAddsCypherRouteFileWhenConfigured(): void {
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = 'bolt://write:7687';
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = 'bolt://read:7687';

		NeoWikiExtension::onExtensionRegistration();

		$this->assertCount( 1, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
		$this->assertStringEndsWith( 'neo4jRoutes.json', $GLOBALS['wgRestAPIAdditionalRouteFiles'][0] );
	}

	public function testAddsNoRouteFileWhenUnconfigured(): void {
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = null;
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = null;

		NeoWikiExtension::onExtensionRegistration();

		$this->assertSame( [], $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testPreservesExistingRouteFiles(): void {
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = [ '/existing/routes.json' ];
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = 'bolt://write:7687';
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = 'bolt://read:7687';

		NeoWikiExtension::onExtensionRegistration();

		$this->assertContains( '/existing/routes.json', $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
		$this->assertCount( 2, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testAddsSparqlRouteFileWhenStoreConfigured(): void {
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = null;
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = null;
		$GLOBALS['wgNeoWikiSparqlStores'] = [ [ 'updateUrl' => 'https://qlever.example/api' ] ];

		NeoWikiExtension::onExtensionRegistration();

		$this->assertCount( 1, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
		$this->assertStringEndsWith( 'sparqlRoutes.json', $GLOBALS['wgRestAPIAdditionalRouteFiles'][0] );
	}

	public function testAddsNoSparqlRouteFileWithoutStore(): void {
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = null;
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = null;
		$GLOBALS['wgNeoWikiSparqlStores'] = [];

		NeoWikiExtension::onExtensionRegistration();

		$this->assertSame( [], $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testAddsBothRouteFilesWhenNeo4jAndSparqlConfigured(): void {
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = 'bolt://write:7687';
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = 'bolt://read:7687';
		$GLOBALS['wgNeoWikiSparqlStores'] = [ [ 'updateUrl' => 'https://qlever.example/api' ] ];

		NeoWikiExtension::onExtensionRegistration();

		$this->assertCount( 2, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

}
