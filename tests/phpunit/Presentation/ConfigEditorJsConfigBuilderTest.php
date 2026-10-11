<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWikiIntegrationTestCase;
use MessageLocalizer;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup;
use ProfessionalWiki\NeoWiki\Presentation\ConfigEditorJsConfigBuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubWikiConfigSource;

/**
 * An integration test for MediaWiki's message rendering, which the labels and help texts go through.
 *
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ConfigEditorJsConfigBuilder
 */
class ConfigEditorJsConfigBuilderTest extends MediaWikiIntegrationTestCase {

	private const array PHP_CONFIG = [
		'NeoWikiSubjectFirst' => false,
		'NeoWikiAutoRenderMainSubject' => true,
		'NeoWikiDataLanguages' => [],
	];

	private function buildFor( ?array $pageConfig, array $phpConfig = self::PHP_CONFIG ): array {
		$context = new RequestContext();
		$context->setLanguage( 'qqx' );

		return $this->newBuilder( $this->newLookup( $pageConfig, $phpConfig ), $context )->build( revisionId: 42 );
	}

	private function newBuilder( WikiConfigLookup $lookup, MessageLocalizer $messageLocalizer ): ConfigEditorJsConfigBuilder {
		return new ConfigEditorJsConfigBuilder( new ConfigSchema(), $lookup, $messageLocalizer );
	}

	private function newLookup( ?array $pageConfig, array $phpConfig ): WikiConfigLookup {
		return new WikiConfigLookup(
			new ConfigSchema(),
			new StubWikiConfigSource( $pageConfig ),
			new HashConfig( $phpConfig ),
			true,
			new NullLogger()
		);
	}

	/**
	 * Gives $text for every message, as a translation would.
	 */
	private function localizerSaying( string $text ): MessageLocalizer {
		return new class( $text ) implements MessageLocalizer {

			public function __construct( private readonly string $text ) {
			}

			public function msg( $key, ...$params ): Message {
				return new RawMessage( $this->text );
			}

		};
	}

	private function settingIn( array $jsConfig, string $pageKey ): array {
		return array_column( $jsConfig['settings'], null, 'key' )[$pageKey];
	}

	public function testListsTheExposedSettingsInTheirOrder(): void {
		$jsConfig = $this->buildFor( null );

		$this->assertTrue( array_is_list( $jsConfig['settings'] ), 'The frontend reads the settings as a JSON array' );
		$this->assertSame(
			[ 'subjectFirst', 'dataLanguages', 'autoRenderMainSubject' ],
			array_column( $jsConfig['settings'], 'key' )
		);
	}

	public function testNamesTheValueTypeOfASetting(): void {
		$jsConfig = $this->buildFor( null );

		$this->assertSame( 'boolean', $this->settingIn( $jsConfig, 'subjectFirst' )['type'] );
		$this->assertSame( 'languageList', $this->settingIn( $jsConfig, 'dataLanguages' )['type'] );
	}

	public function testGivesASettingItsLabelAndHelpText(): void {
		$setting = $this->settingIn( $this->buildFor( null ), 'dataLanguages' );

		$this->assertSame( '(neowiki-config-setting-data-languages)', $setting['label'] );
		$this->assertSame( '(neowiki-config-setting-data-languages-help)', $setting['help'] );
	}

	public function testGivesNoHelpTextForASettingWithout(): void {
		$this->assertNull( $this->settingIn( $this->buildFor( null ), 'autoRenderMainSubject' )['help'] );
	}

	public function testGivesTheValuesASettingOffersByName(): void {
		$choices = $this->settingIn( $this->buildFor( null ), 'subjectFirst' )['choices'];

		$this->assertSame( [ false, true ], array_column( $choices, 'value' ) );
		$this->assertSame(
			[ '(neowiki-config-setting-subject-first-page-first)', '(neowiki-config-setting-subject-first-subject-first)' ],
			array_column( $choices, 'label' )
		);
		$this->assertSame(
			[
				'(neowiki-config-setting-subject-first-page-first-help)',
				'(neowiki-config-setting-subject-first-subject-first-help)',
			],
			array_column( $choices, 'help' )
		);
	}

	public function testGivesItsTextsUnescapedForTheFormToEscape(): void {
		$builder = $this->newBuilder( $this->newLookup( null, self::PHP_CONFIG ), $this->localizerSaying( "The wiki's data" ) );

		$setting = $this->settingIn( $builder->build( revisionId: 42 ), 'subjectFirst' );

		$this->assertSame( "The wiki's data", $setting['label'] );
		$this->assertSame( "The wiki's data", $setting['choices'][0]['help'] );
	}

	public function testGivesTheLocalSettingsValueWhateverThePageSets(): void {
		$jsConfig = $this->buildFor(
			[ 'dataLanguages' => [ 'fr' ] ],
			[ 'NeoWikiDataLanguages' => [ 'de', 'it' ] ] + self::PHP_CONFIG
		);

		$this->assertSame( [ 'de', 'it' ], $this->settingIn( $jsConfig, 'dataLanguages' )['localSettingsValue'] );
	}

	public function testGivesThePageValueOfASettingThePageSets(): void {
		$jsConfig = $this->buildFor( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $this->settingIn( $jsConfig, 'autoRenderMainSubject' )['pageValue'] );
	}

	public function testGivesNoPageValueForASettingThePageDoesNotSet(): void {
		$jsConfig = $this->buildFor( [ 'autoRenderMainSubject' => false ] );

		$this->assertNull( $this->settingIn( $jsConfig, 'subjectFirst' )['pageValue'] );
	}

	public function testGivesNoPageValueForASettingThePageSetsToAValueItDoesNotAccept(): void {
		$jsConfig = $this->buildFor( [ 'subjectFirst' => 'yes' ] );

		$this->assertNull( $this->settingIn( $jsConfig, 'subjectFirst' )['pageValue'] );
	}

}
