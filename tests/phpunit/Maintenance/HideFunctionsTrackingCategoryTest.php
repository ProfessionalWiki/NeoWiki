<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\Content\TextContent;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Maintenance\HideFunctionsTrackingCategory;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use WikiCategoryPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\HideFunctionsTrackingCategory
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onLoadExtensionSchemaUpdates
 * @group Database
 */
class HideFunctionsTrackingCategoryTest extends NeoWikiIntegrationTestCase {

	public function testCreatesTheCategoryAsAHiddenCategory(): void {
		$this->runScript();

		$this->assertTrue( ( new WikiCategoryPage( self::category() ) )->isHidden() );
	}

	public function testLeavesAnExistingCategoryPageAsItIs(): void {
		$this->editPage( self::category(), 'Pages our bot rewrites' );

		$this->runScript();

		$this->assertSame( 'Pages our bot rewrites', $this->textOf( self::category() ) );
	}

	public function testIsDoneWhenTheCategoryPageAlreadyExists(): void {
		$this->editPage( self::category(), 'Pages our bot rewrites' );

		$this->assertTrue( $this->runScript() );
	}

	public function testCreatesNoCategoryPageWhenTheTrackingCategoryIsDisabled(): void {
		$this->overrideConfigValue( MainConfigNames::UseDatabaseMessages, true );
		$this->editPage( Title::makeTitle( NS_MEDIAWIKI, 'Neowiki-functions-category' ), '-' );

		$this->runScript();

		$this->assertFalse( Title::makeTitle( NS_CATEGORY, '-' )->exists() );
	}

	public function testIsNotDoneWhenTheCategoryPageCannotBeCreated(): void {
		$this->setTemporaryHook( 'MultiContentSave', static fn (): bool => false );

		$this->assertFalse( $this->runScript() );
	}

	public function testUpdatePhpRunsIt(): void {
		$postUpdateScripts = [];

		$updater = $this->createMock( DatabaseUpdater::class );
		$updater->method( 'getDB' )->willReturn( $this->getDb() );
		$updater->method( 'addPostDatabaseUpdateMaintenance' )->willReturnCallback(
			static function ( string $script ) use ( &$postUpdateScripts ): void {
				$postUpdateScripts[] = $script;
			}
		);

		$this->getServiceContainer()->getHookContainer()->run( 'LoadExtensionSchemaUpdates', [ $updater ] );

		$this->assertContains( HideFunctionsTrackingCategory::class, $postUpdateScripts );
	}

	private static function category(): Title {
		return Title::makeTitle( NS_CATEGORY, 'Pages_using_NeoWiki_functions' );
	}

	private function runScript(): bool {
		$script = new HideFunctionsTrackingCategory();
		$script->loadWithArgv( [] );

		ob_start();
		try {
			$done = $script->execute();
		} finally {
			$script->cleanupChanneled();
			ob_end_clean();
		}

		DeferredUpdates::doUpdates();

		return $done;
	}

	private function textOf( Title $title ): string {
		$content = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title )->getContent();
		$this->assertInstanceOf( TextContent::class, $content );

		return $content->getText();
	}

}
