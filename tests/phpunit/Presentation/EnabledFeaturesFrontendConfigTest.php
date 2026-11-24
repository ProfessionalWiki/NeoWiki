<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\NeoWikiFeatures;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the database because the loader reads the MediaWiki:NeoWiki configuration page.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::newFrontendModuleLoader
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader
 * @group Database
 */
class EnabledFeaturesFrontendConfigTest extends NeoWikiIntegrationTestCase {

	protected function tearDown(): void {
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	public function testAFeatureOnByDefaultReachesTheFrontend(): void {
		$this->overrideConfigValue( 'NeoWikiFeatures', [] );

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
