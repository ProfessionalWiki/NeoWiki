<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::onExtensionRegistration
 */
class OnExtensionRegistrationTest extends TestCase {

	private string|false $writeOverride;
	private string|false $readOverride;
	private mixed $routeFiles;
	private mixed $writeUrl;
	private mixed $readUrl;

	protected function setUp(): void {
		parent::setUp();
		$this->writeOverride = getenv( 'NEO4J_URL_OVERRIDE' );
		$this->readOverride = getenv( 'NEO4J_URL_READ_OVERRIDE' );
		$this->routeFiles = $GLOBALS['wgRestAPIAdditionalRouteFiles'] ?? null;
		$this->writeUrl = $GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] ?? null;
		$this->readUrl = $GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] ?? null;

		// Clear the CI env overrides so the config-value path is exercised deterministically.
		putenv( 'NEO4J_URL_OVERRIDE' );
		putenv( 'NEO4J_URL_READ_OVERRIDE' );
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = [];
	}

	protected function tearDown(): void {
		putenv( $this->writeOverride === false ? 'NEO4J_URL_OVERRIDE' : "NEO4J_URL_OVERRIDE=$this->writeOverride" );
		putenv( $this->readOverride === false ? 'NEO4J_URL_READ_OVERRIDE' : "NEO4J_URL_READ_OVERRIDE=$this->readOverride" );
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = $this->routeFiles;
		$GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] = $this->writeUrl;
		$GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] = $this->readUrl;
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

}
