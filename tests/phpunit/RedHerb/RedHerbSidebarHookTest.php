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
 */
class RedHerbSidebarHookTest extends MediaWikiIntegrationTestCase {

	public function testAddsCreateChildAndSubjectFinderLinksWhenNoMainSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook( self::pageHasMainSubjectStub( false ) );

		$hook->onSidebarBeforeOutput( $this->newSkin(), $sidebar );

		$this->assertCount( 2, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-create-child-company', $sidebar['redherb-sidebar'][0]['id'] );
		$this->assertSame( 'ext-redherb-create-child-company-trigger', $sidebar['redherb-sidebar'][0]['class'] );
		$this->assertSame( '#', $sidebar['redherb-sidebar'][0]['href'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][1]['id'] );
		$this->assertStringContainsString( 'RedHerbSubjectFinder', $sidebar['redherb-sidebar'][1]['href'] );
	}

	public function testAddsEditMainSubjectLinkWhenPageHasMainSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook( self::pageHasMainSubjectStub( true ) );

		$hook->onSidebarBeforeOutput( $this->newSkin(), $sidebar );

		$this->assertCount( 3, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-edit-main-subject', $sidebar['redherb-sidebar'][2]['id'] );
		$this->assertSame( 'ext-redherb-edit-main-subject-trigger', $sidebar['redherb-sidebar'][2]['class'] );
		$this->assertSame( '#', $sidebar['redherb-sidebar'][2]['href'] );
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
		$this->assertCount( 2, $sidebar['redherb-sidebar'] );
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

}
