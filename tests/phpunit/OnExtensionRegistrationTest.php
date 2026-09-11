<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::onExtensionRegistration
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::poweredByBadge
 */
class OnExtensionRegistrationTest extends TestCase {

	use HandlesNeo4jEnvOverrides;

	private mixed $routeFiles;
	private mixed $writeUrl;
	private mixed $readUrl;
	private mixed $sparqlStores;
	private mixed $footerIcons;
	private mixed $extensionAssetsPath;

	protected function setUp(): void {
		parent::setUp();
		$this->routeFiles = $GLOBALS['wgRestAPIAdditionalRouteFiles'] ?? null;
		$this->writeUrl = $GLOBALS['wgNeoWikiNeo4jInternalWriteUrl'] ?? null;
		$this->readUrl = $GLOBALS['wgNeoWikiNeo4jInternalReadUrl'] ?? null;
		$this->sparqlStores = $GLOBALS['wgNeoWikiSparqlStores'] ?? null;
		$this->footerIcons = $GLOBALS['wgFooterIcons'] ?? null;
		$this->extensionAssetsPath = $GLOBALS['wgExtensionAssetsPath'] ?? null;

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
		$GLOBALS['wgFooterIcons'] = $this->footerIcons;
		$GLOBALS['wgExtensionAssetsPath'] = $this->extensionAssetsPath;
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

	public function testRegistersThePoweredByBadgeForTheRunningMediaWiki(): void {
		$this->givenOnlyTheMediaWikiBadge();

		NeoWikiExtension::onExtensionRegistration();

		$this->assertSame(
			NeoWikiExtension::poweredByBadge( '/w/extensions', MW_VERSION ),
			$GLOBALS['wgFooterIcons']['poweredbyneowiki']['neowiki']
		);
	}

	public function testBadgeBeforeMediaWiki144IsTheFullBadgeOnly(): void {
		$this->assertSame(
			[
				'src' => '/w/extensions/NeoWiki/resources/images/poweredby_neowiki.svg',
				'url' => 'https://neowiki.ai/',
				'alt' => 'Powered by NeoWiki',
				'lang' => 'en',
			],
			NeoWikiExtension::poweredByBadge( '/w/extensions', '1.43.8' )
		);
	}

	public function testBadgeFromMediaWiki144ShowsTheCompactIconOnNarrowScreens(): void {
		$this->assertSame(
			[
				'src' => '/w/extensions/NeoWiki/resources/images/poweredby_neowiki_compact.svg',
				'url' => 'https://neowiki.ai/',
				'alt' => 'Powered by NeoWiki',
				'lang' => 'en',
				'width' => 25,
				'height' => 25,
				'sources' => [
					[
						'media' => '(min-width: 500px)',
						'srcset' => '/w/extensions/NeoWiki/resources/images/poweredby_neowiki.svg',
						'width' => 88,
						'height' => 31,
					],
				],
			],
			NeoWikiExtension::poweredByBadge( '/w/extensions', '1.44.0' )
		);
	}

	public function testMediaWiki144DevelopmentBuildsGetTheCompactIcon(): void {
		$this->assertArrayHasKey( 'sources', NeoWikiExtension::poweredByBadge( '/w/extensions', '1.44.0-alpha' ) );
	}

	public function testAddsTheBadgeAsItsOwnBlockAfterTheMediaWikiOne(): void {
		$this->givenOnlyTheMediaWikiBadge();

		NeoWikiExtension::onExtensionRegistration();

		$this->assertSame( [ 'poweredby', 'poweredbyneowiki' ], array_keys( $GLOBALS['wgFooterIcons'] ) );
	}

	public function testKeepsAPreconfiguredPoweredByBadge(): void {
		$this->givenOnlyTheMediaWikiBadge();
		$GLOBALS['wgFooterIcons']['poweredbyneowiki']['neowiki'] = false;

		NeoWikiExtension::onExtensionRegistration();

		$this->assertFalse( $GLOBALS['wgFooterIcons']['poweredbyneowiki']['neowiki'] );
	}

	public function testBadgeImagesExistWhereTheyAreRegistered(): void {
		$badge = NeoWikiExtension::poweredByBadge( '/w/extensions', '1.44.0' );

		$this->assertFileExists( $this->fileBehind( $badge['src'] ) );
		$this->assertFileExists( $this->fileBehind( $badge['sources'][0]['srcset'] ) );
	}

	private function fileBehind( string $url ): string {
		return dirname( __DIR__, 2 ) . str_replace( '/w/extensions/NeoWiki', '', $url );
	}

	private function givenOnlyTheMediaWikiBadge(): void {
		$GLOBALS['wgExtensionAssetsPath'] = '/w/extensions';
		$GLOBALS['wgFooterIcons'] = [
			'poweredby' => [
				'mediawiki' => [
					'src' => null,
					'url' => 'https://www.mediawiki.org/',
					'alt' => 'Powered by MediaWiki',
				],
			],
		];
	}

}
