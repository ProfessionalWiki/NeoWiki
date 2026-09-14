<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki
 * @group Database
 */
class SpecialNeoWikiTest extends SpecialPageTestBase {

	private const string GRAPH_STORES_ATTRIBUTE = 'data-mw-neowiki-can-manage-graph-stores';
	private const string CONFIGURATION_ATTRIBUTE = 'data-mw-neowiki-can-edit-configuration';

	protected function newSpecialPage(): SpecialNeoWiki {
		return new SpecialNeoWiki();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'id="ext-neowiki-overview"', $output );
	}

	public function testAnAdministratorIsOfferedTheGraphStores(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestSysop()->getUser() );

		$this->assertStringContainsString( self::GRAPH_STORES_ATTRIBUTE . '="true"', $output );
	}

	public function testAUserWithoutTheAdminRightIsNotOfferedTheGraphStores(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringNotContainsString( self::GRAPH_STORES_ATTRIBUTE, $output );
	}

	public function testAnAdministratorIsOfferedTheConfigurationPage(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestSysop()->getUser() );

		$this->assertStringContainsString( self::CONFIGURATION_ATTRIBUTE . '="true"', $output );
	}

	public function testAUserWhoMayNotEditTheConfigurationPageIsNotOfferedIt(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringNotContainsString( self::CONFIGURATION_ATTRIBUTE, $output );
	}

	public function testTheConfigurationPageIsNotOfferedWhileInWikiConfigurationIsDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestSysop()->getUser() );

		$this->assertStringNotContainsString( self::CONFIGURATION_ATTRIBUTE, $output );
	}

	/**
	 * Covers what instantiating the class directly cannot: that extension.json registers the page, and
	 * that it is listed for everyone.
	 */
	public function testThePageIsRegisteredAndListedForEveryone(): void {
		$listed = $this->getServiceContainer()->getSpecialPageFactory()->getListedPages();

		$this->assertArrayHasKey( 'NeoWiki', $listed );
		$this->assertFalse( $listed['NeoWiki']->isRestricted() );
	}

}
