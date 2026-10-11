<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\JsonContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;

/**
 * Viewing MediaWiki:NeoWiki, which shows the form the frontend builds from the configuration it is
 * handed, or leaves the page to core. Needs the database for the configuration page and its revisions.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onConfigPageBeforePageDisplay
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension::newConfigEditorJsConfigBuilder
 * @group Database
 */
class ConfigEditorViewTest extends NeoWikiIntegrationTestCase {

	private const string RENDERED_JSON =
		'<div class="noresize"><table class="mw-json"><tbody><tr><td>data</td></tr></tbody></table></div>';

	private const string RENDERED_DIFF =
		'<table class="diff"><tr><td class="diff-marker">+</td></tr></table>' . self::RENDERED_JSON;

	private const string MISSING_REVISION_ERROR =
		'<div class="errorbox">The revision #999999999 of the page named "MediaWiki:NeoWiki" does not exist.</div>';

	private function configTitle(): Title {
		return Title::makeTitle( NS_MEDIAWIKI, 'NeoWiki' );
	}

	private function saveConfigPage( string $json ): RevisionRecord {
		return $this->editPage( $this->configTitle(), new JsonContent( $json ) )->getNewRevision();
	}

	/**
	 * The page as core renders it, as $html, before this hook runs: showing $revision, or a page that
	 * does not exist when there is none.
	 */
	private function view(
		?RevisionRecord $revision = null,
		array $request = [ 'action' => 'view' ],
		string $html = self::RENDERED_JSON,
		?Title $title = null
	): OutputPage {
		$out = $this->newOutput( $title ?? $this->configTitle(), $request );
		$out->setArticleFlag( true );
		$out->setRevisionId( $revision?->getId() );
		$out->addHTML( $html );

		return $this->display( $out );
	}

	/**
	 * $out as this hook leaves it, just before MediaWiki sends the page.
	 */
	private function display( OutputPage $out ): OutputPage {
		NeoWikiHooks::onConfigPageBeforePageDisplay( $out, $out->getContext()->getSkin() );

		return $out;
	}

	private function newOutput( Title $title, array $request ): OutputPage {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( $request ) );

		return $context->getOutput();
	}

	private function formConfig( OutputPage $out ): array {
		return $out->getJsConfigVars()['wgNeoWikiConfigEditor'];
	}

	private function pageValueIn( OutputPage $out, string $pageKey ): mixed {
		return $this->formConfig( $out )['pageValues'][$pageKey];
	}

	public function testTheCurrentPageShowsTheFormInPlaceOfItsJson(): void {
		$out = $this->view( $this->saveConfigPage( '{ "subjectFirst": true }' ) );

		$this->assertStringContainsString( 'id="ext-neowiki-config-editor"', $out->getHTML() );
		$this->assertStringNotContainsString( 'mw-json', $out->getHTML() );
		$this->assertContains( 'ext.neowiki', $out->getModules() );
	}

	public function testAMissingPageShowsTheForm(): void {
		$this->assertStringContainsString( 'id="ext-neowiki-config-editor"', $this->view()->getHTML() );
	}

	public function testTheLatestRevisionAskedForByIdShowsTheForm(): void {
		$latest = $this->saveConfigPage( '{ "subjectFirst": true }' );

		$out = $this->view( $latest, request: [ 'action' => 'view', 'oldid' => $latest->getId() ] );

		$this->assertStringContainsString( 'id="ext-neowiki-config-editor"', $out->getHTML() );
	}

	public function testTheFormStartsFromTheLatestRevisionWhenAnOlderOneIsPublished(): void {
		$published = $this->saveConfigPage( '{ "subjectFirst": true }' );
		$latest = $this->saveConfigPage( '{ "subjectFirst": false }' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $published ) );

		// A plain view shows the published revision, as an approval extension does for its readers.
		$out = $this->view( $published );

		$this->assertFalse( $this->pageValueIn( $out, 'subjectFirst' ) );
		$this->assertSame( $latest->getId(), $this->formConfig( $out )['revisionId'] );
	}

	public function testTheFormIsHeadedNeoWikiSettings(): void {
		$this->assertSame( 'NeoWiki settings', $this->view()->getPageTitle() );
	}

	public function testAMissingPageHasNoRevisionToSaveOver(): void {
		$this->assertNull( $this->formConfig( $this->view() )['revisionId'] );
	}

	public function testTheBrowserIsToldNotToKeepACopyOfTheForm(): void {
		$out = $this->view( $this->saveConfigPage( '{}' ) );

		$out->sendCacheControl();

		$this->assertStringContainsString( 'no-store', $out->getRequest()->response()->getHeader( 'Cache-Control' ) );
	}

	public function testTheFormLinksToTheDocumentationOfTheConfigurationPage(): void {
		$this->assertStringContainsString(
			'href="https://neowiki.ai/docs/operations/installation#on-wiki-settings"',
			$this->view()->getIndicators()['mw-helplink'] ?? ''
		);
	}

	public function testAViewerWhoMayNotReadThePageGetsTheErrorRatherThanTheForm(): void {
		$this->saveConfigPage( '{ "subjectFirst": true }' );
		$out = $this->newOutput( $this->configTitle(), [ 'action' => 'view' ] );
		$out->showPermissionStatus( PermissionStatus::newFatal( 'badaccess-group0' ), 'read' );

		$this->display( $out );

		$this->assertStringNotContainsString( 'ext-neowiki-config-editor', $out->getHTML() );
		$this->assertArrayNotHasKey( 'wgNeoWikiConfigEditor', $out->getJsConfigVars() );
	}

	public function testAnOlderRevisionIsLeftToCore(): void {
		$older = $this->saveConfigPage( '{ "subjectFirst": true }' );
		$this->saveConfigPage( '{ "subjectFirst": false }' );

		$out = $this->view( $older, request: [ 'action' => 'view', 'oldid' => $older->getId() ] );

		$this->assertSame( self::RENDERED_JSON, $out->getHTML() );
	}

	public function testARevisionAskedForByIdThatCannotBeShownIsLeftToCore(): void {
		$this->saveConfigPage( '{ "subjectFirst": true }' );

		// Core shows no revision then, so the output names none.
		$out = $this->view(
			request: [ 'action' => 'view', 'oldid' => '999999999' ],
			html: self::MISSING_REVISION_ERROR
		);

		$this->assertSame( self::MISSING_REVISION_ERROR, $out->getHTML() );
	}

	public function testADiffIsLeftToCore(): void {
		$out = $this->view( request: [ 'action' => 'view', 'diff' => '2' ], html: self::RENDERED_DIFF );

		$this->assertSame( self::RENDERED_DIFF, $out->getHTML() );
	}

	public function testOtherMediaWikiPagesAreLeftToCore(): void {
		$out = $this->view( title: Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ) );

		$this->assertSame( self::RENDERED_JSON, $out->getHTML() );
	}

	public function testThePageIsLeftToCoreWhenInWikiConfigIsDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$this->assertSame( self::RENDERED_JSON, $this->view()->getHTML() );
	}

}
