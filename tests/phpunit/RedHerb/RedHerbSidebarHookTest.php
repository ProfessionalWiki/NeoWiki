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

	public function testAddsBothCreateChildAndSubjectFinderLinks(): void {
		$sidebar = [];
		$hook = new RedHerbSidebarHook();

		$hook->onSidebarBeforeOutput( $this->createStub( Skin::class ), $sidebar );

		$this->assertCount( 2, $sidebar['redherb-sidebar'] );
		$this->assertSame( 'redherb-sidebar-create-child-company', $sidebar['redherb-sidebar'][0]['id'] );
		$this->assertSame( 'ext-redherb-create-child-company-trigger', $sidebar['redherb-sidebar'][0]['class'] );
		$this->assertSame( '#', $sidebar['redherb-sidebar'][0]['href'] );
		$this->assertSame( 'redherb-sidebar-subject-finder', $sidebar['redherb-sidebar'][1]['id'] );
		$this->assertStringContainsString( 'RedHerbSubjectFinder', $sidebar['redherb-sidebar'][1]['href'] );
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
