<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\ModelBundles;

use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialSchemas;
use SpecialPageExecutor;
use SpecialPageTestBase;

/**
 * Special:NeoWiki reads the database to tell whether the wiki has Subjects, and so does the hook for Schemas.
 *
 * @covers \ProfessionalWiki\NeoWiki\ModelBundles\OverviewNoticeHook
 * @group Database
 */
class OverviewNoticeHookTest extends SpecialPageTestBase {

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
		$output = $this->outputOf( new SpecialSchemas(), $this->getTestSysop()->getAuthority() );

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

	private function outputOf( SpecialPage $page, Authority $performer ): OutputPage {
		( new SpecialPageExecutor() )->executeSpecialPage( $page, '', null, 'en', $performer );

		return $page->getOutput();
	}

}
