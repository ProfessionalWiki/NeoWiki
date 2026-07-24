<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigExample;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onContentHandlerDefaultModelFor
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onEditFilter
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onEditFormPreloadText
 * @group Database
 */
class ConfigPageHooksTest extends MediaWikiIntegrationTestCase {

	private function configTitle(): Title {
		return Title::makeTitle( NS_MEDIAWIKI, 'NeoWiki' );
	}

	private function newEditPage( Title $title ): EditPage {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		return new EditPage( Article::newFromTitle( $title, $context ) );
	}

	private function editFilterError( Title $title, string $text ): string {
		$error = '';
		NeoWikiHooks::onEditFilter( $this->newEditPage( $title ), $text, '', $error, '' );

		return $error;
	}

	private function preloadText( Title $title ): string {
		$text = '';
		NeoWikiHooks::onEditFormPreloadText( $text, $title );

		return $text;
	}

	public function testConfigPageGetsTheJsonContentModel(): void {
		$model = CONTENT_MODEL_WIKITEXT;

		NeoWikiHooks::onContentHandlerDefaultModelFor( $this->configTitle(), $model );

		$this->assertSame( CONTENT_MODEL_JSON, $model );
	}

	public function testOtherMediaWikiPageKeepsItsContentModel(): void {
		$model = CONTENT_MODEL_WIKITEXT;

		NeoWikiHooks::onContentHandlerDefaultModelFor( Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ), $model );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $model );
	}

	public function testContentModelIsNotForcedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$model = CONTENT_MODEL_WIKITEXT;
		NeoWikiHooks::onContentHandlerDefaultModelFor( $this->configTitle(), $model );

		$this->assertSame( CONTENT_MODEL_WIKITEXT, $model );
	}

	public function testValidConfigIsAccepted(): void {
		$this->assertSame(
			'',
			$this->editFilterError( $this->configTitle(), '{ "dereferenceSubjectsToDataTab": true }' )
		);
	}

	public function testWrongTypeForDereferenceIsRejectedWithItsMessage(): void {
		$error = $this->editFilterError( $this->configTitle(), '{ "dereferenceSubjectsToDataTab": "sidebar" }' );

		$this->assertNotSame( '', $error );
		$this->assertStringContainsString( 'dereferenceSubjectsToDataTab', $error );
	}

	public function testUnknownKeyIsRejectedWithItsMessage(): void {
		$error = $this->editFilterError( $this->configTitle(), '{ "NeoWikiSparqlStores": [] }' );

		$this->assertStringContainsString( 'NeoWikiSparqlStores', $error );
	}

	public function testWrongTypeIsRejected(): void {
		$error = $this->editFilterError( $this->configTitle(), '{ "autoRenderMainSubject": "yes" }' );

		$this->assertStringContainsString( 'autoRenderMainSubject', $error );
	}

	public function testEditsToOtherPagesAreNotValidated(): void {
		$this->assertSame(
			'',
			$this->editFilterError( Title::makeTitle( NS_MAIN, 'Some page' ), 'this is not even json' )
		);
	}

	public function testConfigPageIsNotValidatedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$this->assertSame(
			'',
			$this->editFilterError( $this->configTitle(), '{ "dereferenceSubjectsToDataTab": "sidebar" }' )
		);
	}

	public function testConfigPageIsPreloadedWithTheExample(): void {
		$this->assertSame( ConfigExample::JSON, $this->preloadText( $this->configTitle() ) );
	}

	public function testOtherMediaWikiPageIsNotPreloaded(): void {
		$this->assertSame( '', $this->preloadText( Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ) ) );
	}

	public function testConfigPageIsNotPreloadedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$this->assertSame( '', $this->preloadText( $this->configTitle() ) );
	}

	public function testPreloadToleratesTheNullTextCorePassesForAMissingPage(): void {
		// Core's ApiQueryInfo::extractPageInfo() invokes EditFormPreloadText with $text = null for any
		// non-existent page (action=query&prop=info&inprop=preload), so the handler must not fatal on it.
		$text = null;
		NeoWikiHooks::onEditFormPreloadText( $text, Title::makeTitle( NS_MAIN, 'A missing page' ) );

		$this->assertNull( $text );
	}

	public function testConfigPagePreloadFillsNullText(): void {
		$text = null;
		NeoWikiHooks::onEditFormPreloadText( $text, $this->configTitle() );

		$this->assertSame( ConfigExample::JSON, $text );
	}

}
