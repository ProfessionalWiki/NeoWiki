<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use MediaWiki\Output\OutputPage;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\RedHerb\RedHerbFrontendModulesHook;
use Skin;

/**
 * @covers \ProfessionalWiki\RedHerb\RedHerbFrontendModulesHook
 */
class RedHerbFrontendModulesHookTest extends TestCase {

	public function testAddsRedHerbDialogModules(): void {
		$modules = [];
		$hook = new RedHerbFrontendModulesHook();

		$hook->onNeoWikiGetFrontendModules(
			$modules,
			$this->createStub( OutputPage::class ),
			$this->createStub( Skin::class )
		);

		$this->assertSame(
			[ 'ext.redherb-create-child', 'ext.redherb-edit-main-subject' ],
			$modules
		);
	}

	public function testPreservesExistingModules(): void {
		$modules = [ 'ext.preexisting' ];
		$hook = new RedHerbFrontendModulesHook();

		$hook->onNeoWikiGetFrontendModules(
			$modules,
			$this->createStub( OutputPage::class ),
			$this->createStub( Skin::class )
		);

		$this->assertSame(
			[ 'ext.preexisting', 'ext.redherb-create-child', 'ext.redherb-edit-main-subject' ],
			$modules
		);
	}

}
