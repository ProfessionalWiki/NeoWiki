<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSetting
 */
class ConfigSchemaTest extends TestCase {

	public function testExposesTheDereferenceSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'dereferenceSubjectsToDataTab' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'NeoWikiDereferenceSubjectsToDataTab', $setting->settingName );
	}

	public function testExposesTheAutoRenderSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'autoRenderMainSubject' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'NeoWikiAutoRenderMainSubject', $setting->settingName );
	}

	public function testExposesExactlyTheTwoAllowlistedSettings(): void {
		$keys = array_map(
			static fn ( $setting ): string => $setting->pageKey,
			( new ConfigSchema() )->getSettings()
		);

		$this->assertSame( [ 'dereferenceSubjectsToDataTab', 'autoRenderMainSubject' ], $keys );
	}

	public function testUnknownKeyHasNoSetting(): void {
		$this->assertNull( ( new ConfigSchema() )->getSetting( 'NeoWikiSparqlStores' ) );
	}

	public function testSettingAcceptsOnlyRealBooleans(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'dereferenceSubjectsToDataTab' );

		$this->assertTrue( $setting->isValidValue( true ) );
		$this->assertTrue( $setting->isValidValue( false ) );
		$this->assertFalse( $setting->isValidValue( 'true' ) );
		$this->assertFalse( $setting->isValidValue( 1 ) );
		$this->assertFalse( $setting->isValidValue( null ) );
	}

	public function testSettingDescribesItselfAsBoolean(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'dereferenceSubjectsToDataTab' );

		$this->assertSame( [ 'neowiki-config-type-boolean' ], $setting->describe() );
		$this->assertSame(
			[ 'neowiki-config-error-invalid-boolean', 'dereferenceSubjectsToDataTab' ],
			$setting->invalidValueError()
		);
	}

}
