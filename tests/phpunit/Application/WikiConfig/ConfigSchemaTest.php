<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSetting;

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

	/**
	 * The configuration form shows these, so a setting without them shows message keys.
	 *
	 * @dataProvider settingProvider
	 */
	public function testSettingHasItsTextsInEnglish( ConfigSetting $setting ): void {
		$messages = json_decode( (string)file_get_contents( dirname( __DIR__, 4 ) . '/i18n/en.json' ), true );

		$this->assertSame( [], array_values( array_diff( $this->messageKeysOf( $setting ), array_keys( $messages ) ) ) );
	}

	/**
	 * @return string[]
	 */
	private function messageKeysOf( ConfigSetting $setting ): array {
		$keys = [ $setting->labelMessage, $setting->helpMessage ];

		foreach ( $setting->choices as $choice ) {
			$keys[] = $choice->labelMessage;
			$keys[] = $choice->helpMessage;
		}

		return array_filter( $keys, static fn ( ?string $key ): bool => $key !== null );
	}

	public static function settingProvider(): iterable {
		foreach ( ( new ConfigSchema() )->getSettings() as $setting ) {
			yield $setting->pageKey => [ $setting ];
		}
	}

}
