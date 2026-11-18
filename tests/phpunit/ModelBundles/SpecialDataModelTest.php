<?php

namespace ProfessionalWiki\NeoWiki\Tests\ModelBundles;

use ProfessionalWiki\NeoWiki\ModelBundles\SpecialDataModel;
use ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages\HelpLinkAssertions;
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
			$this->outputOf( $this->newSpecialPage() )->getJsConfigVars()
		);
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
