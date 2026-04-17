<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Skin;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks
 * @group Database
 */
class NeoWikiHooksSidebarTest extends NeoWikiIntegrationTestCase {

	private const SECTION_KEY = 'neowiki-page-tools-label';

	public function testAddsBothItemsWhenDevUiEnabledAndNoSubjects(): void {
		$this->overrideConfigValue( 'NeoWikiEnableDevelopmentUI', true );

		$title = Title::newFromText( 'NeoWikiSidebarBothItems' );
		$this->editPage( $title, 'content' );

		$sidebar = $this->runSidebarHook( $title );

		$this->assertSame(
			[
				$this->createSubjectItem(),
				$this->editJsonItem( $title ),
			],
			$sidebar[ self::SECTION_KEY ] ?? null
		);
	}

	public function testAddsOnlyCreateSubjectWhenDevUiDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableDevelopmentUI', false );

		$title = Title::newFromText( 'NeoWikiSidebarDevUiOff' );
		$this->editPage( $title, 'content' );

		$sidebar = $this->runSidebarHook( $title );

		$this->assertSame(
			[ $this->createSubjectItem() ],
			$sidebar[ self::SECTION_KEY ] ?? null
		);
	}

	public function testAddsOnlyJsonLinkWhenPageHasSubjects(): void {
		$this->overrideConfigValue( 'NeoWikiEnableDevelopmentUI', true );

		$this->createPageWithSubjects(
			pageName: 'NeoWikiSidebarHasSubjects',
			mainSubject: TestSubject::build()
		);
		$title = Title::newFromText( 'NeoWikiSidebarHasSubjects' );

		$sidebar = $this->runSidebarHook( $title );

		$this->assertSame(
			[ $this->editJsonItem( $title ) ],
			$sidebar[ self::SECTION_KEY ] ?? null
		);
	}

	public function testAddsOnlyJsonLinkWhenViewingOldRevision(): void {
		$this->overrideConfigValue( 'NeoWikiEnableDevelopmentUI', true );

		$title = Title::newFromText( 'NeoWikiSidebarOldRevision' );
		$this->editPage( $title, 'content' );

		$nonLatestRevisionId = $title->getLatestRevID() - 1;

		$sidebar = $this->runSidebarHook( $title, $nonLatestRevisionId );

		$this->assertSame(
			[ $this->editJsonItem( $title ) ],
			$sidebar[ self::SECTION_KEY ] ?? null
		);
	}

	public function testOmitsSectionWhenDevUiOffAndPageHasSubjects(): void {
		$this->overrideConfigValue( 'NeoWikiEnableDevelopmentUI', false );

		$this->createPageWithSubjects(
			pageName: 'NeoWikiSidebarDevUiOffWithSubjects',
			mainSubject: TestSubject::build()
		);
		$title = Title::newFromText( 'NeoWikiSidebarDevUiOffWithSubjects' );

		$sidebar = $this->runSidebarHook( $title );

		$this->assertArrayNotHasKey( self::SECTION_KEY, $sidebar );
	}

	public function testOmitsSectionOnNonContentPage(): void {
		$this->overrideConfigValue( 'NeoWikiEnableDevelopmentUI', true );

		$title = Title::newFromText( 'SomeUser', NS_USER );

		$sidebar = $this->runSidebarHook( $title );

		$this->assertArrayNotHasKey( self::SECTION_KEY, $sidebar );
	}

	public function testPreservesLayoutsToolOnLayoutNamespace(): void {
		$title = Title::newFromText( 'TestLayout', NeoWikiExtension::NS_LAYOUT );

		$sidebar = $this->runSidebarHook( $title );

		$this->assertSame(
			[
				[
					'text' => wfMessage( 'neowiki-layout-sidebar-all-layouts' )->text(),
					'href' => SpecialPage::getTitleFor( 'Layouts' )->getLocalURL(),
					'id' => 't-neowiki-layouts',
				],
			],
			$sidebar['TOOLBOX'] ?? null
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function runSidebarHook( Title $title, ?int $viewingRevisionId = null ): array {
		$authority = $this->getTestSysop()->getUser();

		$output = $this->createStub( OutputPage::class );
		$output->method( 'getRevisionId' )->willReturn( $viewingRevisionId );

		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( $title );
		$skin->method( 'getOutput' )->willReturn( $output );
		$skin->method( 'getAuthority' )->willReturn( $authority );

		$sidebar = [];
		NeoWikiHooks::onSidebarBeforeOutput( $skin, $sidebar );

		return $sidebar;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createSubjectItem(): array {
		return [
			'text' => wfMessage( 'neowiki-page-tools-create-subject' )->text(),
			'href' => '#',
			'id' => 't-neowiki-create-subject',
			'data' => [
				'mw-neowiki-action' => 'open-subject-creator',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function editJsonItem( Title $title ): array {
		return [
			'text' => wfMessage( 'neowiki-page-tools-edit-json' )->text(),
			'href' => SpecialPage::getTitleFor( 'NeoJson', $title->getFullText() )->getFullURL(),
			'id' => 't-neowiki-edit-json',
		];
	}

}
