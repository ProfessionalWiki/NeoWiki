<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use Closure;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\RedHerb\RedHerbSidebarHook;
use Skin;

/**
 * @covers \ProfessionalWiki\RedHerb\RedHerbSidebarHook
 * @group Database
 */
class RedHerbSidebarHookTest extends MediaWikiIntegrationTestCase {

	public function testAddsCreateChildLinkOnAnyExistingPage(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook( self::pageHasMainSubjectStub( false ) );

		$hook->onSidebarBeforeOutput( $this->newSkinForExistingPage(), $sidebar );

		$this->assertCount( 2, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
		$this->assertSame( 'redherb-sidebar-create-child-company', $sidebar['redherb-sidebar'][1]['id'] );
		$this->assertSame( 'ext-redherb-create-child-company-trigger', $sidebar['redherb-sidebar'][1]['class'] );
	}

	public function testAddsEditLinkOnPageWithMainSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook( self::pageHasMainSubjectStub( true ) );

		$hook->onSidebarBeforeOutput( $this->newSkinForExistingPage(), $sidebar );

		$this->assertCount( 3, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-edit-main-subject', $sidebar['redherb-sidebar'][2]['id'] );
		$this->assertSame( 'ext-redherb-edit-main-subject-trigger', $sidebar['redherb-sidebar'][2]['class'] );
	}

	public function testOnlyAddsSubjectFinderLinkOnNonExistentPages(): void {
		$sidebar = [];
		$predicateInvoked = false;
		$hook = new RedHerbSidebarHook( static function () use ( &$predicateInvoked ): bool {
			$predicateInvoked = true;
			return true;
		} );

		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( Title::newFromText( 'NonExistentPage_' . uniqid() ) );

		$hook->onSidebarBeforeOutput( $skin, $sidebar );

		$this->assertFalse( $predicateInvoked );
		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
	}

	public function testDoesNotCheckMainSubjectForNonExistingTitles(): void {
		$sidebar = [];
		$predicateInvoked = false;
		$hook = new RedHerbSidebarHook( static function () use ( &$predicateInvoked ): bool {
			$predicateInvoked = true;
			return true;
		} );

		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( Title::newFromText( 'UserLogin', NS_SPECIAL ) );

		$hook->onSidebarBeforeOutput( $skin, $sidebar );

		$this->assertFalse( $predicateInvoked );
		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
	}

	public function testDoesNotOverwriteExistingSidebarSections(): void {
		$sidebar = [ 'navigation' => [ [ 'id' => 'preexisting' ] ] ];
		$hook = new RedHerbSidebarHook( self::pageHasMainSubjectStub( false ) );

		$hook->onSidebarBeforeOutput( $this->newSkin(), $sidebar );

		$this->assertArrayHasKey( 'navigation', $sidebar );
		$this->assertSame( 'preexisting', $sidebar['navigation'][0]['id'] );
		$this->assertArrayHasKey( 'redherb-sidebar', $sidebar );
	}

	private static function pageHasMainSubjectStub( bool $value ): Closure {
		return static fn ( Title $title ): bool => $value;
	}

	private function newSkin(): Skin {
		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( Title::newFromText( 'Test' ) );
		return $skin;
	}

	private function newSkinForExistingPage(): Skin {
		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( $this->getExistingTestPage()->getTitle() );
		return $skin;
	}

}
