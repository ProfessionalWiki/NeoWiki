<?php

namespace ProfessionalWiki\NeoWiki\Tests\ModelBundles;

use ProfessionalWiki\NeoWiki\ModelBundles\SpecialDataModel;
use ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages\HelpLinkAssertions;
use SpecialPageExecutor;
use SpecialPageTestBase;

/**
 * Listing the special pages builds each of them, and some read the database as they are built.
 *
 * @covers \ProfessionalWiki\NeoWiki\ModelBundles\SpecialDataModel
 * @group Database
 */
class SpecialDataModelTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	protected function newSpecialPage(): SpecialDataModel {
		return new SpecialDataModel();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'id="ext-neowiki-data-model"',
			$output
		);
	}

	public function testLoadsTheModelBundlesModule(): void {
		$this->assertContains( 'ext.neowiki.modelBundles', $this->outputOf( $this->newSpecialPage() )->getModules() );
	}

	public function testHandsTheFrontendTheCatalogsAndThePreselection(): void {
		$this->overrideConfigValues( [
			'NeoWikiModelBundleCatalogs' => [ 'https://example.org/catalog.json' ],
			'NeoWikiModelBundlePreselection' => [ 'Core' ],
		] );

		$this->assertArrayContains(
			[
				'wgNeoWikiModelBundleCatalogs' => [ 'https://example.org/catalog.json' ],
				'wgNeoWikiModelBundlePreselection' => [ 'Core' ],
			],
			$this->outputOf( $this->newSpecialPage(), $this->getTestSysop()->getAuthority() )->getJsConfigVars()
		);
	}

	public function testTellsTheCatalogsToNoUserWhoMayNotInstall(): void {
		$this->overrideConfigValues( [
			'NeoWikiModelBundleCatalogs' => [ 'https://example.org/catalog.json' ],
			'NeoWikiModelBundlePreselection' => [ 'Core' ],
		] );

		$variables = $this->outputOf( $this->newSpecialPage(), $this->getTestUser()->getAuthority() )->getJsConfigVars();

		$this->assertSame(
			[ false, false ],
			[ array_key_exists( 'wgNeoWikiModelBundleCatalogs', $variables ), array_key_exists( 'wgNeoWikiModelBundlePreselection', $variables ) ]
		);
	}

	public function testOpensTheSetupOnItsSubpage(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( 'setup' );

		$this->assertStringContainsString( 'data-mw-neowiki-view="setup"', $output );
	}

	public function testTitlesTheSetupAsSuch(): void {
		$page = $this->newSpecialPage();
		( new SpecialPageExecutor() )->executeSpecialPage( $page, 'setup', null, 'qqx' );

		$this->assertSame( '(neowiki-bundle-setup-title)', $page->getOutput()->getPageTitle() );
	}

	public function testHandsTheSetupWhatTheWikiHolds(): void {
		$setup = $this->outputOf( $this->newSpecialPage(), $this->getTestSysop()->getAuthority() )
			->getJsConfigVars()['wgNeoWikiModelBundlesSetup'];

		$this->assertEquals(
			[ 'wikiHasSchemas' => false, 'wikiHasSubjects' => false, 'canEditSettings' => true ],
			array_diff_key( $setup, [ 'settings' => null ] )
		);
		$this->assertArrayHasKey( 'pageValues', $setup['settings'] );
	}

	public function testHandsTheSetupTheRevisionOfTheSettingsPageToSaveOver(): void {
		$revisionId = $this->editPage( 'MediaWiki:NeoWiki', '{}' )->getNewRevision()->getId();

		$setup = $this->outputOf( $this->newSpecialPage(), $this->getTestSysop()->getAuthority() )
			->getJsConfigVars()['wgNeoWikiModelBundlesSetup'];

		$this->assertSame( $revisionId, $setup['settings']['revisionId'] );
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$this->assertHelpLinkLeadsToTheDocs( $this->outputOf( $this->newSpecialPage() ) );
	}

	/**
	 * Covers what instantiating the class directly cannot: that extension-model-bundles.json registers the page,
	 * and that it is listed for everyone.
	 */
	public function testThePageIsRegisteredAndListedForEveryone(): void {
		$listed = $this->getServiceContainer()->getSpecialPageFactory()->getListedPages();

		$this->assertArrayHasKey( 'DataModel', $listed );
		$this->assertFalse( $listed['DataModel']->isRestricted() );
	}

}
