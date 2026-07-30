<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Presentation\ConfigDocumentationBuilder;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ConfigDocumentationBuilder
 * @group Database
 */
class ConfigDocumentationBuilderTest extends MediaWikiIntegrationTestCase {

	private function newBuilder(): ConfigDocumentationBuilder {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( Title::makeTitle( NS_MEDIAWIKI, 'NeoWiki' ) );

		return new ConfigDocumentationBuilder( new ConfigSchema(), $context );
	}

	private function overrideAcceptedValueMessage( string $wikitext ): void {
		$this->overrideConfigValue( MainConfigNames::UseDatabaseMessages, true );
		$this->editPage( Title::makeTitle( NS_MEDIAWIKI, 'Neowiki-config-type-boolean' ), $wikitext );
	}

	public function testReferenceListsEverySettingAndTheLocalSettingsNameItOverrides(): void {
		$html = $this->newBuilder()->buildReference();

		foreach ( ( new ConfigSchema() )->getSettings() as $setting ) {
			$this->assertStringContainsString( '>' . $setting->pageKey . '<', $html );
			$this->assertStringContainsString( '$wg' . $setting->settingName, $html );
		}
	}

	public function testReferenceShowsBooleanAcceptedValuesAsCodeSpans(): void {
		$html = $this->newBuilder()->buildReference();

		$this->assertStringContainsString( '<code>true</code>', $html );
		$this->assertStringContainsString( '<code>false</code>', $html );
	}

	public function testReferenceDoesNotWrapTheSettingKeysInCode(): void {
		// The key and LocalSettings.php columns fill their cell, so they are plain text, not code chips.
		$this->assertStringContainsString( '<td>dereferenceSubjectsToDataTab</td>', $this->newBuilder()->buildReference() );
	}

	public function testReferenceCarriesTheAnchorThePointerLinksTo(): void {
		$this->assertStringContainsString(
			'id="' . ConfigDocumentationBuilder::ANCHOR . '"',
			$this->newBuilder()->buildReference()
		);
	}

	public function testPointerLinksToTheReferenceAndTheDocumentation(): void {
		$html = $this->newBuilder()->buildPointer();

		$this->assertStringContainsString( '#' . ConfigDocumentationBuilder::ANCHOR, $html );
		$this->assertStringContainsString( 'neowiki.ai/docs/operations/installation', $html );
	}

	public function testReferenceShowsScriptTagsFromAnOverriddenMessageAsText(): void {
		$this->overrideAcceptedValueMessage( '<script>alert(1)</script>' );

		$html = $this->newBuilder()->buildReference();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}

	public function testReferenceDropsEventHandlersFromAnOverriddenMessage(): void {
		$this->overrideAcceptedValueMessage( '<code onmouseover="alert(1)">yes</code>' );

		$html = $this->newBuilder()->buildReference();

		$this->assertStringNotContainsString( 'onmouseover', $html );
		$this->assertStringContainsString( '<code>yes</code>', $html );
	}

}
