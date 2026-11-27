<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Integration test: the settings are MediaWiki config, which only the integration base class overrides.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 */
class EdtfRegistrationTest extends NeoWikiIntegrationTestCase {

	protected function tearDown(): void {
		NeoWikiExtension::resetInstance();
		parent::tearDown();
	}

	public function testEdtfIsAPropertyTypeWhereTheWikiEnablesIt(): void {
		$this->setEdtfEnabled( true );

		$this->assertNotNull( NeoWikiExtension::getInstance()->getPropertyTypeLookup()->getType( 'edtf' ) );
	}

	public function testEdtfIsNoPropertyTypeWhereTheWikiDoesNotEnableIt(): void {
		$this->setEdtfEnabled( false );

		$this->assertNull( NeoWikiExtension::getInstance()->getPropertyTypeLookup()->getType( 'edtf' ) );
	}

	public function testEdtfValuesProjectToRdfWhereTheWikiEnablesIt(): void {
		$this->setEdtfEnabled( true );

		$this->assertTrue( NeoWikiExtension::getInstance()->getRdfValueMapperRegistry()->hasMapper( 'edtf' ) );
	}

	public function testEdtfValuesStayOutOfRdfWhereTheWikiDoesNotEnableIt(): void {
		$this->setEdtfEnabled( false );

		$this->assertFalse( NeoWikiExtension::getInstance()->getRdfValueMapperRegistry()->hasMapper( 'edtf' ) );
	}

	public function testEdtfValuesProjectToNeo4jWhereTheWikiEnablesIt(): void {
		$this->setEdtfEnabled( true );

		$this->assertTrue( NeoWikiExtension::getInstance()->getValueBuilderRegistry()->hasBuilder( 'edtf' ) );
	}

	public function testEdtfValuesStayOutOfNeo4jWhereTheWikiDoesNotEnableIt(): void {
		$this->setEdtfEnabled( false );

		$this->assertFalse( NeoWikiExtension::getInstance()->getValueBuilderRegistry()->hasBuilder( 'edtf' ) );
	}

	private function setEdtfEnabled( bool $enabled ): void {
		$this->overrideConfigValues( [
			'NeoWikiEnabledPropertyTypes' => $enabled ? [ 'edtf' ] : [],
			'NeoWikiEnableExperimentalFeatures' => false,
		] );
	}

}
