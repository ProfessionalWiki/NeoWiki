<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialNeoWiki
 * @group Database
 */
class SpecialNeoWikiTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	private const string GRAPH_STORES_ATTRIBUTE = 'data-mw-neowiki-can-manage-graph-stores';
	private const string CONFIGURATION_ATTRIBUTE = 'data-mw-neowiki-can-edit-configuration';
	private const string SUBJECTS_ATTRIBUTE = 'data-mw-neowiki-wiki-has-subjects';

	protected function newSpecialPage(): SpecialNeoWiki {
		return new SpecialNeoWiki();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( 'id="ext-neowiki-overview"', $output );
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$output = $this->outputOf( $this->newSpecialPage(), $this->getTestUser()->getUser() );

		$this->assertHelpLinkLeadsToTheDocs( $output );
	}

	public function testLoadsTheFrontendModule(): void {
		$this->assertContains(
			'ext.neowiki',
			$this->outputOf( $this->newSpecialPage(), $this->getTestUser()->getUser() )->getModules()
		);
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

	public function testFlagsAWikiWithSubjects(): void {
		$this->indexASubjectOn( $this->getExistingTestPage()->getId() );

		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringContainsString( self::SUBJECTS_ATTRIBUTE . '="true"', $output );
	}

	public function testDoesNotFlagAWikiWithoutSubjects(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );

		$this->assertStringNotContainsString( self::SUBJECTS_ATTRIBUTE, $output );
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

	private function indexASubjectOn( int $pageId ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( DatabaseSubjectPageIndex::TABLE )
			->row( [ 'nwsp_subject_id' => 's11111111111111', 'nwsp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
	}

}
