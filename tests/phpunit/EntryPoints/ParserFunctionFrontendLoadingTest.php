<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * A parser function can put NeoWiki UI on any page, including one none of the frontend-loading page kinds cover.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBeforePageDisplay
 * @group Database
 */
class ParserFunctionFrontendLoadingTest extends NeoWikiIntegrationTestCase {

	public function testAddsTheFrontendConfigVarsWhenWikitextAskedForTheModule(): void {
		$out = $this->displayPage( Title::makeTitle( NS_HELP, 'Carries a button' ), requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgNeoWikiValidationDebounceMs', $out->getJsConfigVars() );
		$this->assertArrayHasKey( 'wgNeoWikiEnforceValidation', $out->getJsConfigVars() );
	}

	public function testAddsNoFrontendConfigVarsWithoutTheModule(): void {
		$out = $this->displayPage( Title::makeTitle( NS_HELP, 'Carries nothing' ), requestsTheFrontend: false );

		$this->assertArrayNotHasKey( 'wgNeoWikiValidationDebounceMs', $out->getJsConfigVars() );
	}

	public function testAddsTheDeniedReasonWhenWikitextAskedForTheModule(): void {
		$out = $this->displayPage( Title::makeTitle( NS_HELP, 'Carries a button' ), requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgNeoWikiCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	public function testAddsTheDeniedReasonToAContentPageCarryingAButton(): void {
		$out = $this->displayPage( Title::makeTitle( NS_MAIN, 'Carries a button' ), requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgNeoWikiCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	public function testAddsNoDeniedReasonToAContentPageWithoutAButton(): void {
		$out = $this->displayPage( Title::makeTitle( NS_MAIN, 'Carries nothing' ), requestsTheFrontend: false );

		$this->assertArrayNotHasKey( 'wgNeoWikiCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	public function testAddsNoDeniedReasonToASpecialPageThatLoadedTheFrontend(): void {
		$out = $this->displayPage( Title::makeTitle( NS_SPECIAL, 'Schemas' ), requestsTheFrontend: true );

		$this->assertArrayNotHasKey( 'wgNeoWikiCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	private function displayPage( Title $title, bool $requestsTheFrontend ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( $title );

		$out = $context->getOutput();
		$out->setArticleFlag( !$title->isSpecialPage() );

		if ( $requestsTheFrontend ) {
			// What a parser function's ParserOutput, or a special page loading the frontend itself, has added by then.
			$out->addModules( [ 'ext.neowiki' ] );
		}

		NeoWikiHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

}
