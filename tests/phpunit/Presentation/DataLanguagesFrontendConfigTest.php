<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Content\JsonContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * The data languages the frontend gets wherever it loads, as every page loading it goes through the
 * extension's frontend module loader. Needs the database for the MediaWiki:NeoWiki configuration page.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::getDataLanguages
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader
 * @group Database
 */
class DataLanguagesFrontendConfigTest extends MediaWikiIntegrationTestCase {

	protected function tearDown(): void {
		// Drops the memoized configuration page, so a later test does not inherit it.
		NeoWikiExtension::resetInstance();

		parent::tearDown();
	}

	public function testTheConfigurationPageDataLanguagesReachTheFrontend(): void {
		$this->overrideConfigValue( 'NeoWikiDataLanguages', [ 'en' ] );
		$this->saveConfigPage( '{ "dataLanguages": [ "fr", "de", "it" ] }' );

		$this->assertSame( [ 'fr', 'de', 'it' ], $this->frontendDataLanguages() );
	}

	public function testMalformedLocalSettingsDataLanguagesReachTheFrontendAsNone(): void {
		$this->overrideConfigValue( 'NeoWikiDataLanguages', 'de, fr' );

		$this->assertSame( [], $this->frontendDataLanguages() );
	}

	private function saveConfigPage( string $json ): void {
		$this->editPage( Title::makeTitle( NS_MEDIAWIKI, NeoWikiExtension::CONFIG_PAGE_TITLE ), new JsonContent( $json ) );

		NeoWikiExtension::resetInstance();
	}

	private function frontendDataLanguages(): mixed {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Data languages' ) );

		$out = $context->getOutput();
		NeoWikiExtension::getInstance()->newFrontendModuleLoader()->load( $out, $context->getSkin() );

		return $out->getJsConfigVars()['wgNeoWikiDataLanguages'] ?? null;
	}

}
