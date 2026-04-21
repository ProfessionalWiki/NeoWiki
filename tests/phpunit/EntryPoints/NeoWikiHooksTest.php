<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Skin;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks
 */
class NeoWikiHooksTest extends MediaWikiIntegrationTestCase {

	public function testAddsCoreModuleWhenNoExtensionsHandleHook(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$out = $this->newSchemaOutputPageMock();
		$skin = $this->createMock( Skin::class );

		$addedModules = [];
		$out->method( 'addModules' )->willReturnCallback(
			static function ( string|array $modules ) use ( &$addedModules ): void {
				$addedModules = array_merge( $addedModules, (array)$modules );
			}
		);

		NeoWikiHooks::onBeforePageDisplay( $out, $skin );

		$this->assertSame( [ 'ext.neowiki' ], $addedModules );
	}

	public function testAppendsModulesContributedByExtensions(): void {
		$out = $this->newSchemaOutputPageMock();
		$skin = $this->createMock( Skin::class );

		$addedModules = [];
		$out->method( 'addModules' )->willReturnCallback(
			static function ( string|array $modules ) use ( &$addedModules ): void {
				$addedModules = array_merge( $addedModules, (array)$modules );
			}
		);

		$this->setTemporaryHook(
			'NeoWikiGetFrontendModules',
			static function ( array &$modules, OutputPage $out, Skin $skin ): void {
				$modules[] = 'ext.redherb-test';
			}
		);

		NeoWikiHooks::onBeforePageDisplay( $out, $skin );

		$this->assertSame( [ 'ext.neowiki', 'ext.redherb-test' ], $addedModules );
	}

	public function testPassesOutputAndSkinToHookHandlers(): void {
		$out = $this->newSchemaOutputPageMock();
		$skin = $this->createMock( Skin::class );

		$receivedOut = null;
		$receivedSkin = null;
		$this->setTemporaryHook(
			'NeoWikiGetFrontendModules',
			static function ( array &$modules, OutputPage $hookOut, Skin $hookSkin )
				use ( &$receivedOut, &$receivedSkin ): void {
				$receivedOut = $hookOut;
				$receivedSkin = $hookSkin;
			}
		);

		NeoWikiHooks::onBeforePageDisplay( $out, $skin );

		$this->assertSame( $out, $receivedOut );
		$this->assertSame( $skin, $receivedSkin );
	}

	private function newSchemaOutputPageMock(): OutputPage {
		$title = Title::makeTitle( NeoWikiExtension::NS_SCHEMA, 'TestSchema' );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'getTitle' )->willReturn( $title );
		$out->method( 'isArticle' )->willReturn( true );

		return $out;
	}

}
