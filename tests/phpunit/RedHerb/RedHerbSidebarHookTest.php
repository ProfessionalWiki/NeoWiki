<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use Closure;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\RedHerb\RedHerbSidebarHook;
use Skin;

/**
 * @covers \ProfessionalWiki\RedHerb\RedHerbSidebarHook
 * @group Database
 */
class RedHerbSidebarHookTest extends MediaWikiIntegrationTestCase {

	public function testAddsCreateChildLinkWhenUserCanCreateChildSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook(
			self::pageHasMainSubjectStub( false ),
			self::canEditSubjectStub( true ),
			self::canCreateChildSubjectStub( true )
		);

		$hook->onSidebarBeforeOutput( $this->newSkinForExistingPage(), $sidebar );

		$this->assertCount( 2, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
		$this->assertSame( 'redherb-sidebar-create-child-company', $sidebar['redherb-sidebar'][1]['id'] );
		$this->assertSame( 'ext-redherb-create-child-company-trigger', $sidebar['redherb-sidebar'][1]['class'] );
	}

	public function testOmitsCreateChildLinkWhenUserCannotCreateChildSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook(
			self::pageHasMainSubjectStub( false ),
			self::canEditSubjectStub( true ),
			self::canCreateChildSubjectStub( false )
		);

		$hook->onSidebarBeforeOutput( $this->newSkinForExistingPage(), $sidebar );

		$ids = array_column( $sidebar['redherb-sidebar'], 'id' );
		$this->assertNotContains( 'redherb-sidebar-create-child-company', $ids );
	}

	public function testAddsEditLinkWhenUserCanEditAndPageHasMainSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook(
			self::pageHasMainSubjectStub( true ),
			self::canEditSubjectStub( true ),
			self::canCreateChildSubjectStub( true )
		);

		$hook->onSidebarBeforeOutput( $this->newSkinForExistingPage(), $sidebar );

		$this->assertCount( 3, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-edit-main-subject', $sidebar['redherb-sidebar'][2]['id'] );
		$this->assertSame( 'ext-redherb-edit-main-subject-trigger', $sidebar['redherb-sidebar'][2]['class'] );
	}

	public function testOmitsEditLinkWhenUserCannotEditSubject(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook(
			self::pageHasMainSubjectStub( true ),
			self::canEditSubjectStub( false ),
			self::canCreateChildSubjectStub( true )
		);

		$hook->onSidebarBeforeOutput( $this->newSkinForExistingPage(), $sidebar );

		$ids = array_column( $sidebar['redherb-sidebar'], 'id' );
		$this->assertNotContains( 'redherb-sidebar-edit-main-subject', $ids );
	}

	public function testOnlyAddsSubjectFinderLinkOnNonExistentPages(): void {
		$sidebar = [];
		$predicateInvoked = false;
		$hook = new RedHerbSidebarHook( static function () use ( &$predicateInvoked ): bool {
			$predicateInvoked = true;
			return true;
		} );

		$hook->onSidebarBeforeOutput(
			$this->newSkinStub( Title::newFromText( 'NonExistentPage_' . uniqid() ) ),
			$sidebar
		);

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

		$hook->onSidebarBeforeOutput(
			$this->newSkinStub( Title::newFromText( 'UserLogin', NS_SPECIAL ) ),
			$sidebar
		);

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

	private static function canEditSubjectStub( bool $value ): Closure {
		return static fn ( Authority $authority ): bool => $value;
	}

	private static function canCreateChildSubjectStub( bool $value ): Closure {
		return static fn ( Authority $authority ): bool => $value;
	}

	private function newSkin(): Skin {
		return $this->newSkinStub( Title::newFromText( 'Test' ) );
	}

	private function newSkinForExistingPage(): Skin {
		return $this->newSkinStub( $this->getExistingTestPage()->getTitle() );
	}

	private function newSkinStub( Title $title ): Skin {
		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( $title );
		$skin->method( 'msg' )->willReturnCallback(
			static fn ( string $key ): Message => new RawMessage( $key )
		);
		return $skin;
	}

}
