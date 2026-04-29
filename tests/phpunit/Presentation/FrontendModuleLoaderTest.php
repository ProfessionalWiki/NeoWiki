<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Output\OutputPage;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader;
use Skin;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader
 */
class FrontendModuleLoaderTest extends MediaWikiIntegrationTestCase {

	/** @var array<int, string> */
	private array $addedModules = [];

	/** @var array<int, string> */
	private array $addedModuleStyles = [];

	public function testAddsCoreModuleWhenNoExtensionsHandleHook(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( [ 'ext.neowiki' ], $this->addedModules );
	}

	public function testAddsCoreModuleStyles(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( [ 'ext.neowiki.styles' ], $this->addedModuleStyles );
	}

	public function testAppendsModulesContributedByExtensions(): void {
		$this->setTemporaryHook(
			'NeoWikiGetFrontendModules',
			static function ( array &$modules ): void {
				$modules[] = 'ext.redherb-test';
			}
		);

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( [ 'ext.neowiki', 'ext.redherb-test' ], $this->addedModules );
	}

	public function testPassesOutputAndSkinToHookHandlers(): void {
		$out = $this->createMock( OutputPage::class );
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

		$this->newLoader()->load( $out, $skin );

		$this->assertSame( $out, $receivedOut );
		$this->assertSame( $skin, $receivedSkin );
	}

	private function newLoader(): FrontendModuleLoader {
		return new FrontendModuleLoader( $this->getServiceContainer()->getHookContainer() );
	}

	private function newCapturingOutputPage(): OutputPage {
		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' )->willReturnCallback(
			function ( string|array $modules ): void {
				$this->addedModules = array_merge( $this->addedModules, (array)$modules );
			}
		);
		$out->method( 'addModuleStyles' )->willReturnCallback(
			function ( string|array $modules ): void {
				$this->addedModuleStyles = array_merge( $this->addedModuleStyles, (array)$modules );
			}
		);
		return $out;
	}

}
