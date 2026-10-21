<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use SkinTemplate;

/**
 * Needs the database for the pages whose tabs are drawn.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onSkinTemplateNavigationUniversal
 * @group Database
 */
class EditTextTabTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'EditTextTabTest page';

	/**
	 * The tabs as VisualEditor leaves them, its hook running before NeoWiki's.
	 */
	private const array VISUAL_EDITOR_TABS = [
		'view' => [ 'text' => 'Read' ],
		've-edit' => [ 'text' => 'Edit' ],
		'edit' => [ 'text' => 'Edit source' ],
	];

	private const array CORE_TABS = [
		'view' => [ 'text' => 'Read' ],
		'edit' => [ 'text' => 'Edit' ],
	];

	public function testASubjectFirstWikiNamesTheVisualEditorTabEditText(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->editPage( self::PAGE_NAME, 'Text of the page' );

		$this->assertSame(
			[ 've-edit' => '(neowiki-edit-text-tab)', 'edit' => 'Edit source' ],
			$this->editTabsOf( self::PAGE_NAME, self::VISUAL_EDITOR_TABS )
		);
	}

	public function testASubjectFirstWikiNamesTheEditTabEditTextWhereVisualEditorAddedNone(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->editPage( self::PAGE_NAME, 'Text of the page' );

		$this->assertSame( [ 'edit' => '(neowiki-edit-text-tab)' ], $this->editTabsOf( self::PAGE_NAME, self::CORE_TABS ) );
	}

	public function testAPageFirstWikiKeepsTheEditTabs(): void {
		$this->editPage( self::PAGE_NAME, 'Text of the page' );

		$this->assertSame(
			[ 've-edit' => 'Edit', 'edit' => 'Edit source' ],
			$this->editTabsOf( self::PAGE_NAME, self::VISUAL_EDITOR_TABS )
		);
	}

	public function testASubjectFirstWikiKeepsTheEditTabOfAPageOutsideTheContentNamespaces(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->editPage( 'Help:' . self::PAGE_NAME, 'Text of the page' );

		$this->assertSame( [ 'edit' => 'Edit' ], $this->editTabsOf( 'Help:' . self::PAGE_NAME, self::CORE_TABS ) );
	}

	/**
	 * As Special:WhatLinksHere does for the page it is about.
	 */
	public function testASubjectFirstWikiNamesTheEditTabEditTextOnASpecialPageShowingThePagesTabs(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->editPage( self::PAGE_NAME, 'Text of the page' );
		$skin = $this->skinOn( SpecialPage::getTitleFor( 'Whatlinkshere', self::PAGE_NAME ) );
		$skin->setRelevantTitle( Title::newFromText( self::PAGE_NAME ) );

		$this->assertSame( [ 'edit' => '(neowiki-edit-text-tab)' ], $this->editTabsDrawnBy( $skin, self::CORE_TABS ) );
	}

	/**
	 * That tab creates the page, which has no Subject yet.
	 */
	public function testASubjectFirstWikiKeepsTheCreateTabOfAPageThatDoesNotExist(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );

		$this->assertSame(
			[ 'edit' => 'Create' ],
			$this->editTabsOf( self::PAGE_NAME, [ 'view' => [ 'text' => 'Read' ], 'edit' => [ 'text' => 'Create' ] ] )
		);
	}

	public function testASubjectFirstWikiAddsNoEditTabForAReaderWhoMayNotEdit(): void {
		$this->overrideConfigValue( 'NeoWikiSubjectFirst', true );
		$this->editPage( self::PAGE_NAME, 'Text of the page' );

		$this->assertSame(
			[],
			$this->editTabsOf( self::PAGE_NAME, [ 'view' => [ 'text' => 'Read' ], 'viewsource' => [ 'text' => 'View source' ] ] )
		);
	}

	/**
	 * @param array<string, array{text: string}> $views
	 * @return array<string, string>
	 */
	private function editTabsOf( string $pageName, array $views ): array {
		return $this->editTabsDrawnBy( $this->skinOn( Title::newFromText( $pageName ) ), $views );
	}

	private function skinOn( Title $title ): SkinTemplate {
		$context = new RequestContext();
		$context->setLanguage( 'qqx' );
		$context->setTitle( $title );

		$skin = new SkinTemplate( 'fallback' );
		$skin->setContext( $context );

		return $skin;
	}

	/**
	 * The text of each tab that edits the page, by its key, once NeoWiki has drawn the tabs.
	 *
	 * @param array<string, array{text: string}> $views The tabs as the hooks before NeoWiki's leave them
	 * @return array<string, string>
	 */
	private function editTabsDrawnBy( SkinTemplate $skin, array $views ): array {
		$links = [ 'views' => $views ];

		NeoWikiHooks::onSkinTemplateNavigationUniversal( $skin, $links );

		return array_map(
			static fn ( array $tab ): string => $tab['text'],
			array_intersect_key( $links['views'], array_flip( [ 've-edit', 'edit' ] ) )
		);
	}

}
