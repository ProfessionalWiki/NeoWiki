<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onAlternateEdit
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onConfigPageBeforePageDisplay
 * @group Database
 */
class ConfigPageFramingHooksTest extends MediaWikiIntegrationTestCase {

	private const string SEED_HTML =
		'<div id="intro">Default intro</div>'
		. '<div class="noresize"><table class="mw-json"><tbody><tr><td>data</td></tr></tbody></table></div>';

	private const string DIFF_HTML =
		'<table class="diff"><tr><td class="diff-marker">+</td></tr></table>'
		. '<div class="noresize"><table class="mw-json"><tbody><tr><td>data</td></tr></tbody></table></div>';

	private function configTitle(): Title {
		return Title::makeTitle( NS_MEDIAWIKI, 'NeoWiki' );
	}

	private function englishContext( Title $title ): RequestContext {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		return $context;
	}

	private function runAlternateEdit( Title $title ): EditPage {
		$editPage = new EditPage( Article::newFromTitle( $title, $this->englishContext( $title ) ) );
		NeoWikiHooks::onAlternateEdit( $editPage );

		return $editPage;
	}

	public function testConfigPageEditIsFramedWithTheReference(): void {
		$editPage = $this->runAlternateEdit( $this->configTitle() );

		$this->assertTrue( $editPage->suppressIntro );
		$this->assertStringContainsString( 'neowiki.ai', $editPage->editFormTextTop );
		$this->assertStringContainsString( '$wgNeoWikiDereferenceSubjectsToDataTab', $editPage->editFormTextBottom );
	}

	public function testOtherMediaWikiPageEditIsNotFramed(): void {
		$editPage = $this->runAlternateEdit( Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ) );

		$this->assertFalse( $editPage->suppressIntro );
		$this->assertSame( '', $editPage->editFormTextBottom );
	}

	public function testConfigPageEditIsNotFramedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$editPage = $this->runAlternateEdit( $this->configTitle() );

		$this->assertFalse( $editPage->suppressIntro );
		$this->assertSame( '', $editPage->editFormTextBottom );
	}

	private function renderView( Title $title, string $action ): string {
		return $this->renderViewWithRequest( $title, [ 'action' => $action ], self::SEED_HTML );
	}

	private function renderViewWithRequest( Title $title, array $requestParams, string $seedHtml ): string {
		$context = $this->englishContext( $title );
		$context->setRequest( new FauxRequest( $requestParams ) );

		$out = $context->getOutput();
		$out->addHTML( $seedHtml );

		NeoWikiHooks::onConfigPageBeforePageDisplay( $out, $context->getSkin() );

		return $out->getHTML();
	}

	public function testConfigPageViewIsTrimmedToTheJsonTableAndFramed(): void {
		$html = $this->renderView( $this->configTitle(), 'view' );

		$this->assertStringNotContainsString( 'Default intro', $html );
		$this->assertStringContainsString( '<table class="mw-json"', $html );
		$this->assertStringContainsString( 'neowiki.ai', $html );
		$this->assertStringContainsString( '$wgNeoWikiDereferenceSubjectsToDataTab', $html );
	}

	public function testFramedConfigViewEmitsBalancedDivs(): void {
		$html = $this->renderView( $this->configTitle(), 'view' );

		$this->assertSame(
			substr_count( $html, '<div' ),
			substr_count( $html, '</div>' ),
			'The framed config view must not leak the closing tag of the core JSON table wrapper.'
		);
	}

	public function testConfigPageDiffIsNotFramed(): void {
		$html = $this->renderViewWithRequest(
			$this->configTitle(),
			[ 'action' => 'view', 'diff' => '2' ],
			self::DIFF_HTML
		);

		$this->assertStringContainsString( 'diff-marker', $html );
		$this->assertStringNotContainsString( '$wgNeoWikiDereferenceSubjectsToDataTab', $html );
	}

	public function testConfigPageIsUntouchedForNonViewActions(): void {
		$html = $this->renderView( $this->configTitle(), 'history' );

		$this->assertStringContainsString( 'Default intro', $html );
		$this->assertStringNotContainsString( '$wgNeoWikiDereferenceSubjectsToDataTab', $html );
	}

	public function testOtherMediaWikiPageViewIsUntouched(): void {
		$html = $this->renderView( Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ), 'view' );

		$this->assertStringContainsString( 'Default intro', $html );
		$this->assertStringNotContainsString( '$wgNeoWikiDereferenceSubjectsToDataTab', $html );
	}

	public function testConfigPageViewIsUntouchedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$html = $this->renderView( $this->configTitle(), 'view' );

		$this->assertStringContainsString( 'Default intro', $html );
		$this->assertStringNotContainsString( '$wgNeoWikiDereferenceSubjectsToDataTab', $html );
	}

}
