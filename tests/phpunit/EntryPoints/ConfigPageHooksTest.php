<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\UltimateAuthority;
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

	public function testConfigPageHasNoNamespaceTabs(): void {
		$links = $this->navigationOf( $this->configTitle() );

		$this->assertSame( [], $links['associated-pages'] );
		$this->assertSame( [], $links['namespaces'] );
	}

	public function testConfigPageOffersItsFormAsEditAndItsJsonAsEditSourceToSomeoneWhoMayEdit(): void {
		$views = $this->navigationOf( $this->configTitle(), new UltimateAuthority( $this->getTestUser()->getUser() ) )['views'];

		$this->assertSame( 'Edit', $views['view']['text'] );
		$this->assertSame( 'Edit source', $views['edit']['text'] );
	}

	public function testConfigPageKeepsReadForSomeoneWhoMayNotEdit(): void {
		$views = $this->navigationOf( $this->configTitle() )['views'];

		$this->assertSame( 'Read', $views['view']['text'] );
	}

	public function testOtherMediaWikiPageKeepsItsNamespaceTab(): void {
		$links = $this->navigationOf( Title::makeTitle( NS_MEDIAWIKI, 'NotNeoWiki' ) );

		$this->assertArrayHasKey( 'mediawiki', $links['associated-pages'] );
	}

	/**
	 * The tabs MediaWiki gives a MediaWiki: page, as NeoWiki leaves them. Skins draw them from
	 * associated-pages, or from namespaces, its older copy.
	 */
	private function navigationOf( Title $title, ?Authority $authority = null ): array {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		if ( $authority !== null ) {
			$context->setAuthority( $authority );
		}

		$skin = new SkinTemplate( 'fallback' );
		$skin->setContext( $context );
		$tabs = [ 'mediawiki' => [ 'text' => 'Message' ], 'mediawiki_talk' => [ 'text' => 'Discussion' ] ];
		$links = [
			'associated-pages' => $tabs,
			'namespaces' => $tabs,
			'views' => [ 'view' => [ 'text' => 'Read' ], 'edit' => [ 'text' => 'Edit' ] ],
		];

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
