<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use SkinTemplate;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onContentHandlerDefaultModelFor
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onEditFilter
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSkinTemplateNavigationUniversal
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
			$this->editFilterError( $this->configTitle(), '{ "subjectFirst": true }' )
		);
	}

	public function testWrongTypeForSubjectFirstIsRejectedWithItsMessage(): void {
		$error = $this->editFilterError( $this->configTitle(), '{ "subjectFirst": "sidebar" }' );

		$this->assertNotSame( '', $error );
		$this->assertStringContainsString( 'subjectFirst', $error );
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

	public function testConfigPageHasNoNamespaceTabBesideItsDiscussionTab(): void {
		$links = $this->navigationOf( $this->configTitle() );

		$this->assertSame( [ 'mediawiki_talk' ], array_keys( $links['associated-pages'] ) );
		$this->assertSame( [ 'mediawiki_talk' ], array_keys( $links['namespaces'] ) );
	}

	public function testOtherMediaWikiPageKeepsItsNamespaceTab(): void {
		$links = $this->navigationOf( Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ) );

		$this->assertArrayHasKey( 'mediawiki', $links['associated-pages'] );
	}

	/**
	 * The tabs MediaWiki gives a MediaWiki: page, as NeoWiki leaves them. Skins draw them from
	 * associated-pages, or from namespaces, its older copy.
	 */
	private function navigationOf( Title $title ): array {
		$context = new RequestContext();
		$context->setTitle( $title );
		$skin = new SkinTemplate( 'fallback' );
		$skin->setContext( $context );
		$tabs = [ 'mediawiki' => [ 'text' => 'Message' ], 'mediawiki_talk' => [ 'text' => 'Discussion' ] ];
		$links = [ 'associated-pages' => $tabs, 'namespaces' => $tabs, 'views' => [] ];

		NeoWikiHooks::onSkinTemplateNavigationUniversal( $skin, $links );

		return $links;
	}

	public function testConfigPageIsNotValidatedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$this->assertSame(
			'',
			$this->editFilterError( $this->configTitle(), '{ "subjectFirst": "sidebar" }' )
		);
	}

}
