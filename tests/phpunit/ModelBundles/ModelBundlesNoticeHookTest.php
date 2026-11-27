<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ModelBundles;

use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialLayouts;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialSchemas;
use SpecialPageExecutor;
use SpecialPageTestBase;

/**
 * Special:NeoWiki reads the database to tell whether the wiki has Subjects, and so does the hook for Schemas and
 * install records.
 *
 * @covers \ProfessionalWiki\NeoWiki\ModelBundles\ModelBundlesNoticeHook
 * @group Database
 */
class ModelBundlesNoticeHookTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'NeoWikiModelBundleCatalogs', [ 'https://example.org/catalog.json' ] );
	}

	protected function newSpecialPage(): SpecialNeoWiki {
		return new SpecialNeoWiki();
	}

	public function testLoadsModelBundlesOnSpecialNeoWikiForAUserWhoMayInstallThem(): void {
		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestSysop()->getAuthority() );

		$this->assertContains( 'ext.neowiki.modelBundles', $output->getModules() );
	}

	public function testLoadsNothingForAUserWhoMayNotInstallModelBundles(): void {
		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestUser()->getAuthority() );

		$this->assertNotContains( 'ext.neowiki.modelBundles', $output->getModules() );
	}

	public function testLoadsNothingOnOtherNeoWikiPages(): void {
		$output = $this->outputOf( new SpecialLayouts(), $this->getTestSysop()->getAuthority() );

		$this->assertNotContains( 'ext.neowiki.modelBundles', $output->getModules() );
	}

	public function testLoadsModelBundlesOnSpecialSchemasWhileTheSetupIsOffered(): void {
		$output = $this->outputOf( new SpecialSchemas(), $this->getTestSysop()->getAuthority() );

		$this->assertSame(
			[ true, true ],
			[ in_array( 'ext.neowiki.modelBundles', $output->getModules() ), $output->getJsConfigVars()['wgNeoWikiModelBundlesSetupOffered'] ]
		);
	}

	public function testLoadsNothingOnSpecialSchemasOnceTheWikiHasASchema(): void {
		$this->editPage( 'Schema:Museum', '{ "propertyDefinitions": {} }' );

		$output = $this->outputOf( new SpecialSchemas(), $this->getTestSysop()->getAuthority() );

		$this->assertNotContains( 'ext.neowiki.modelBundles', $output->getModules() );
	}

	public function testLoadsNothingOnSpecialSchemasForAUserWhoMayNotInstallModelBundles(): void {
		$output = $this->outputOf( new SpecialSchemas(), $this->getTestUser()->getAuthority() );

		$this->assertNotContains( 'ext.neowiki.modelBundles', $output->getModules() );
	}

	public function testOffersTheSetupWhileTheWikiHasNoSchemas(): void {
		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestSysop()->getAuthority() );

		$this->assertTrue( $output->getJsConfigVars()['wgNeoWikiModelBundlesSetupOffered'] );
	}

	public function testOffersNoSetupOnceTheWikiHasASchema(): void {
		$this->editPage( 'Schema:Museum', '{ "propertyDefinitions": {} }' );

		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestSysop()->getAuthority() );

		$this->assertFalse( $output->getJsConfigVars()['wgNeoWikiModelBundlesSetupOffered'] );
	}

	public function testOffersNoSetupOnAWikiThatNamesNoCatalog(): void {
		$this->overrideConfigValue( 'NeoWikiModelBundleCatalogs', [] );

		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestSysop()->getAuthority() );

		$this->assertFalse( $output->getJsConfigVars()['wgNeoWikiModelBundlesSetupOffered'] );
	}

	public function testLoadsNothingOnSpecialSchemasOfAWikiThatNamesNoCatalog(): void {
		$this->overrideConfigValue( 'NeoWikiModelBundleCatalogs', [] );

		$output = $this->outputOf( new SpecialSchemas(), $this->getTestSysop()->getAuthority() );

		$this->assertNotContains( 'ext.neowiki.modelBundles', $output->getModules() );
	}

	public function testOffersNoSetupOnceABundleIsInstalled(): void {
		$this->editPage( 'MediaWiki:NeoWiki/Model bundles/Mappings.json', '{ "items": [] }' );

		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestSysop()->getAuthority() );

		$this->assertFalse( $output->getJsConfigVars()['wgNeoWikiModelBundlesSetupOffered'] );
	}

	public function testOffersTheSetupBesideOtherInterfacePages(): void {
		$this->editPage( 'MediaWiki:NeoWiki/Model', '{}' );

		$output = $this->outputOf( new SpecialNeoWiki(), $this->getTestSysop()->getAuthority() );

		$this->assertTrue( $output->getJsConfigVars()['wgNeoWikiModelBundlesSetupOffered'] );
	}

	private function outputOf( SpecialPage $page, Authority $performer ): OutputPage {
		( new SpecialPageExecutor() )->executeSpecialPage( $page, '', null, 'en', $performer );

		return $page->getOutput();
	}

}
