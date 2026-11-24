<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\NeoWikiFeatures;

/**
 * The features the frontend learns are on, as every page loading it goes through the extension's frontend
 * module loader. Needs the database because the loader reads the MediaWiki:NeoWiki configuration page.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::newFrontendModuleLoader
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader
 * @group Database
 */
class EnabledFeaturesFrontendConfigTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		NeoWikiExtension::resetInstance();
	}

	protected function tearDown(): void {
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	public function testAFeatureOnByDefaultReachesTheFrontend(): void {
		$this->assertContains( NeoWikiFeatures::LUA, $this->frontendEnabledFeatures() );
	}

	public function testAFeatureTheWikiTurnsOffDoesNotReachTheFrontend(): void {
		$this->overrideConfigValue( 'NeoWikiFeatures', [ NeoWikiFeatures::LUA => false ] );

		$this->assertNotContains( NeoWikiFeatures::LUA, $this->frontendEnabledFeatures() );
	}

	/**
	 * @return string[]
	 */
	private function frontendEnabledFeatures(): array {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Enabled features' ) );

		$out = $context->getOutput();
		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $context->getSkin() );

		return $out->getJsConfigVars()['wgNeoWikiEnabledFeatures'] ?? [];
	}

}
