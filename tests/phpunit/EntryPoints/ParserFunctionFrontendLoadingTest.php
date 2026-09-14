<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * A parser function can put NeoWiki UI on a page none of the frontend-loading page kinds cover.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 */
class ParserFunctionFrontendLoadingTest extends NeoWikiIntegrationTestCase {

	public function testAddsTheFrontendConfigVarsWhenWikitextAskedForTheModule(): void {
		$out = $this->displayPage( 'Carries a button', requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgNeoWikiValidationDebounceMs', $out->getJsConfigVars() );
		$this->assertArrayHasKey( 'wgNeoWikiEnforceValidation', $out->getJsConfigVars() );
	}

	public function testAddsNoFrontendConfigVarsWithoutTheModule(): void {
		$out = $this->displayPage( 'Carries nothing', requestsTheFrontend: false );

		$this->assertArrayNotHasKey( 'wgNeoWikiValidationDebounceMs', $out->getJsConfigVars() );
	}

	private function displayPage( string $pageName, bool $requestsTheFrontend ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_HELP, $pageName ) );

		$out = $context->getOutput();
		$out->setArticleFlag( true );

		if ( $requestsTheFrontend ) {
			// What the parser function's own ParserOutput contributes by the time the page is displayed.
			$out->addModules( [ 'ext.neowiki' ] );
		}

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

}
