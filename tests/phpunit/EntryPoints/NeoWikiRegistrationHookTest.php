<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

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

	public function testRedHerbRegistersDateTimePropertyType(): void {
		$registry = NeoWikiExtension::getInstance()->getPropertyTypeRegistry();

		$this->assertNotNull( $registry->getType( 'dateTime' ) );
	}

	public function testRedHerbRegistersDateTimeNeo4jValueBuilder(): void {
		$registry = NeoWikiExtension::getInstance()->getValueBuilderRegistry();

		$this->assertTrue( $registry->hasBuilder( 'dateTime' ) );
	}

}
