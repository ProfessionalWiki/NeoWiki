<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\RedHerb\RedHerbSidebarHook;
use Skin;

/**
 * @covers \ProfessionalWiki\RedHerb\RedHerbSidebarHook
 * @group Database
 */
class RedHerbSidebarHookTest extends NeoWikiIntegrationTestCase {

	use MockAuthorityTrait;

	private const string PAGE_WITH_MAIN_SUBJECT = 'RedHerbSidebarHookTest_WithSubject';
	private const string PAGE_WITHOUT_SUBJECTS = 'RedHerbSidebarHookTest_NoSubject';

	public function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	public function testAddsCreateChildLinkOnExistingPage(): void {
		$this->createPageWithSubjects( self::PAGE_WITHOUT_SUBJECTS );
		$sidebar = [];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub(
				Title::newFromText( self::PAGE_WITHOUT_SUBJECTS ),
				$this->mockRegisteredAuthorityWithPermissions( [ 'edit' ] )
			),
			$sidebar
		);

		$this->assertCount( 2, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
		$this->assertSame( 'redherb-sidebar-create-child-company', $sidebar['redherb-sidebar'][1]['id'] );
		$this->assertSame( 'ext-redherb-create-child-company-trigger', $sidebar['redherb-sidebar'][1]['class'] );
	}

	public function testAddsEditLinkWhenUserCanEditAndPageHasMainSubject(): void {
		$this->createPageWithSubjects( self::PAGE_WITH_MAIN_SUBJECT, TestSubject::build() );
		$sidebar = [];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub(
				Title::newFromText( self::PAGE_WITH_MAIN_SUBJECT ),
				$this->mockRegisteredAuthorityWithPermissions( [ 'edit' ] )
			),
			$sidebar
		);

		$this->assertCount( 3, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-edit-main-subject', $sidebar['redherb-sidebar'][2]['id'] );
		$this->assertSame( 'ext-redherb-edit-main-subject-trigger', $sidebar['redherb-sidebar'][2]['class'] );
	}

	public function testDoesNotAddEditLinkWhenUserCannotEditSubject(): void {
		$this->createPageWithSubjects( self::PAGE_WITH_MAIN_SUBJECT, TestSubject::build() );
		$sidebar = [];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub(
				Title::newFromText( self::PAGE_WITH_MAIN_SUBJECT ),
				$this->mockAnonAuthorityWithPermissions( [] )
			),
			$sidebar
		);

		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
	}

	public function testDoesNotAddCreateChildLinkWhenUserCannotCreateChildSubject(): void {
		$this->createPageWithSubjects( self::PAGE_WITHOUT_SUBJECTS );
		$sidebar = [];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub(
				Title::newFromText( self::PAGE_WITHOUT_SUBJECTS ),
				$this->mockAnonAuthorityWithPermissions( [] )
			),
			$sidebar
		);

		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
	}

	public function testOnlyAddsSubjectFinderLinkOnNonExistentPages(): void {
		$sidebar = [];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub( Title::newFromText( 'NonExistentPage_' . uniqid() ) ),
			$sidebar
		);

		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
	}

	public function testOnlyAddsSubjectFinderLinkOnNonExistingSpecialPages(): void {
		$sidebar = [];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub( Title::newFromText( 'UserLogin', NS_SPECIAL ) ),
			$sidebar
		);

		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
	}

	public function testDoesNotOverwriteExistingSidebarSections(): void {
		$sidebar = [ 'navigation' => [ [ 'id' => 'preexisting' ] ] ];

		( new RedHerbSidebarHook() )->onSidebarBeforeOutput(
			$this->newSkinStub( Title::newFromText( 'NonExistentPage_' . uniqid() ) ),
			$sidebar
		);

		$this->assertArrayHasKey( 'navigation', $sidebar );
		$this->assertSame( 'preexisting', $sidebar['navigation'][0]['id'] );
		$this->assertArrayHasKey( 'redherb-sidebar', $sidebar );
	}

	private function newSkinStub( Title $title, ?Authority $authority = null ): Skin {
		$skin = $this->createStub( Skin::class );
		$skin->method( 'getTitle' )->willReturn( $title );
		$skin->method( 'getAuthority' )->willReturn(
			$authority ?? $this->mockRegisteredAuthorityWithPermissions( [ 'edit' ] )
		);
		$skin->method( 'msg' )->willReturnCallback(
			static fn ( string $key ): Message => new RawMessage( $key )
		);
		return $skin;
	}

}
