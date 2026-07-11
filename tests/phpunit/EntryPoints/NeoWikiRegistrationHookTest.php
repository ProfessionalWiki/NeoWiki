<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\RedHerb\RedHerbGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class NeoWikiRegistrationHookTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'RedHerb' ) ) {
			$this->markTestSkipped( 'RedHerb extension is not loaded' );
		}
	}

	public function testRedHerbRegistersPropertyType(): void {
		$registry = NeoWikiExtension::getInstance()->getPropertyTypeRegistry();

		$this->assertNotNull( $registry->getType( 'color' ) );
	}

	public function testRedHerbRegistersPagePropertyProvider(): void {
		$providers = NeoWikiExtension::getInstance()->getPagePropertyProviderRegistry()->getProviders();

		$this->assertGreaterThan( 1, count( $providers ), 'Should have more than just the core provider' );
	}

	public function testRedHerbRegistersNeo4jValueBuilder(): void {
		$registry = NeoWikiExtension::getInstance()->getValueBuilderRegistry();

		$this->assertTrue( $registry->hasBuilder( 'color' ) );
	}

	public function testRedHerbRegistersRdfValueMapper(): void {
		$registry = NeoWikiExtension::getInstance()->getRdfValueMapperRegistry();

		$this->assertTrue( $registry->hasMapper( 'color' ) );
	}

	public function testRedHerbRegistersGraphDatabasePlugin(): void {
		$plugins = NeoWikiExtension::getInstance()->getGraphDatabasePluginRegistry()->getPlugins();

		// The registry holds extension-contributed plugins only; core Neo4j is composed separately.
		$this->assertCount( 1, $plugins );
		$this->assertContainsOnlyInstancesOf( RedHerbGraphDatabasePlugin::class, $plugins );
	}

}
