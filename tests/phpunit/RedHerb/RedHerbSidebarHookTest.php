<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\RedHerb\RedHerbSidebarHook;
use Skin;

/**
 * @covers \ProfessionalWiki\RedHerb\RedHerbSidebarHook
 */
class RedHerbSidebarHookTest extends MediaWikiIntegrationTestCase {

	public function testAddsRedHerbSectionWithSubjectFinderLink(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook();

		$hook->onSidebarBeforeOutput( $this->createStub( Skin::class ), $sidebar );

		$this->assertArrayHasKey( 'redherb-sidebar', $sidebar );
		$this->assertCount( 1, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][0]['id'] );
		$this->assertStringContainsString( 'RedHerbSubjectFinder', $sidebar['redherb-sidebar'][0]['href'] );
	}

	public function testDoesNotOverwriteExistingSidebarSections(): void {
		$sidebar = [ 'navigation' => [ [ 'id' => 'preexisting' ] ] ];
		$hook = new RedHerbSidebarHook();

		$hook->onSidebarBeforeOutput( $this->createStub( Skin::class ), $sidebar );

		$this->assertArrayHasKey( 'navigation', $sidebar );
		$this->assertSame( 'preexisting', $sidebar['navigation'][0]['id'] );
		$this->assertArrayHasKey( 'redherb-sidebar', $sidebar );
	}

}
