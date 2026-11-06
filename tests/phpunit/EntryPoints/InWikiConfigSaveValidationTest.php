<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\JsonContent;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * Saves pages through MediaWiki, which needs the database.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onJsonValidateSave
 * @group Database
 */
class InWikiConfigSaveValidationTest extends MediaWikiIntegrationTestCase {

	private function saveConfigPage( string $json ): StatusValue {
		return $this->saveJsonPage( 'NeoWiki', $json );
	}

	private function saveJsonPage( string $pageName, string $json ): StatusValue {
		return $this->editPage( Title::makeTitle( NS_MEDIAWIKI, $pageName ), new JsonContent( $json ) );
	}

	/**
	 * The first error is the one MediaWiki's REST API reports.
	 */
	private function firstErrorText( StatusValue $status ): string {
		$errors = $status->getMessages( 'error' );

		return $errors === [] ? '' : Message::newFromSpecifier( $errors[0] )->inLanguage( 'en' )->text();
	}

	public function testValidSettingsAreSaved(): void {
		$this->assertStatusGood( $this->saveConfigPage( '{ "subjectFirst": true }' ) );
	}

	public function testInvalidSettingsAreNotSaved(): void {
		$this->assertStatusError( 'neowiki-config-invalid', $this->saveConfigPage( '{ "subjectFirst": "sidebar" }' ) );
	}

	public function testTheFirstErrorNamesEveryInvalidSetting(): void {
		$firstError = $this->firstErrorText( $this->saveConfigPage( '{ "nope": true, "subjectFirst": "sidebar" }' ) );

		$this->assertStringContainsString( 'nope', $firstError );
		$this->assertStringContainsString( 'subjectFirst', $firstError );
	}

	public function testTheErrorNamesAnUnknownSettingAsWritten(): void {
		$this->assertStringContainsString(
			'{{SITENAME}}',
			$this->firstErrorText( $this->saveConfigPage( '{ "{{SITENAME}}": true }' ) )
		);
	}

	public function testOtherJsonPagesAreNotChecked(): void {
		$this->assertStatusGood( $this->saveJsonPage( 'NotNeoWiki.json', '{ "subjectFirst": "sidebar" }' ) );
	}

	public function testSettingsAreNotCheckedWhenInWikiConfigIsDisabled(): void {
		$this->overrideConfigValue( 'NeoWikiEnableInWikiConfig', false );

		$this->assertStatusGood( $this->saveConfigPage( '{ "subjectFirst": "sidebar" }' ) );
	}

}
