<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Content\JsonContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::newFrontendModuleLoader
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader
 * @group Database
 */
class InitialConstraintSeverityFrontendConfigTest extends MediaWikiIntegrationTestCase {

	protected function tearDown(): void {
		// Drops the memoized configuration page, so a later test does not inherit it.
		NeoWikiExtension::resetInstance();

		parent::tearDown();
	}

	public function testTheConfigurationPageSeverityReachesTheFrontend(): void {
		$this->overrideConfigValue( 'NeoWikiInitialConstraintSeverity', 'warning' );
		$this->saveConfigPage( '{ "initialConstraintSeverity": "error" }' );

		$this->assertSame( 'error', $this->frontendInitialConstraintSeverity() );
	}

	private function saveConfigPage( string $json ): void {
		$this->editPage( Title::makeTitle( NS_MEDIAWIKI, NeoWikiExtension::CONFIG_PAGE_TITLE ), new JsonContent( $json ) );

		NeoWikiExtension::resetInstance();
	}

	private function frontendInitialConstraintSeverity(): mixed {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Initial constraint severity' ) );

		$out = $context->getOutput();
		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $context->getSkin() );

		return $out->getJsConfigVars()['wgNeoWikiInitialConstraintSeverity'] ?? null;
	}

}
