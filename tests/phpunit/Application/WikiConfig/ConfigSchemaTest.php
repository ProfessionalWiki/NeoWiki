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

	public function testExposesTheSubjectFirstSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'subjectFirst' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'NeoWikiSubjectFirst', $setting->settingName );
	}

	public function testExposesTheAutoRenderSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'autoRenderMainSubject' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'NeoWikiAutoRenderMainSubject', $setting->settingName );
	}

	public function testExposesTheDataLanguagesSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'dataLanguages' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'NeoWikiDataLanguages', $setting->settingName );
	}

	public function testExposesExactlyTheAllowlistedSettings(): void {
		$keys = array_map(
			static fn ( $setting ): string => $setting->pageKey,
			( new ConfigSchema() )->getSettings()
		);

		$this->assertEqualsCanonicalizing( [ 'subjectFirst', 'autoRenderMainSubject', 'dataLanguages' ], $keys );
	}

	public function testUnknownKeyHasNoSetting(): void {
		$this->assertNull( ( new ConfigSchema() )->getSetting( 'NeoWikiSparqlStores' ) );
	}

}
