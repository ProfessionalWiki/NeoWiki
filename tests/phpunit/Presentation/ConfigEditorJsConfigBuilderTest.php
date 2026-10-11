<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Config\HashConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup;
use ProfessionalWiki\NeoWiki\Presentation\ConfigEditorJsConfigBuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubWikiConfigSource;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\ConfigEditorJsConfigBuilder
 */
class ConfigEditorJsConfigBuilderTest extends TestCase {

	private const array PHP_CONFIG = [
		'NeoWikiSubjectFirst' => false,
		'NeoWikiAutoRenderMainSubject' => true,
		'NeoWikiDataLanguages' => [],
	];

	private function buildFor( ?array $pageConfig, array $phpConfig = self::PHP_CONFIG ): array {
		$builder = new ConfigEditorJsConfigBuilder(
			new ConfigSchema(),
			new WikiConfigLookup(
				new ConfigSchema(),
				new StubWikiConfigSource( $pageConfig ),
				new HashConfig( $phpConfig ),
				true,
				new NullLogger()
			)
		);

		return $builder->build( revisionId: 42 );
	}

	public function testGivesTheExposedSettingsInTheirOrder(): void {
		$jsConfig = $this->buildFor( null );

		$this->assertSame( [ 'subjectFirst', 'dataLanguages', 'autoRenderMainSubject' ], array_keys( $jsConfig['pageValues'] ) );
		$this->assertSame(
			[ 'subjectFirst', 'dataLanguages', 'autoRenderMainSubject' ],
			array_keys( $jsConfig['localSettingsValues'] )
		);
	}

	public function testGivesThePageValueOfASettingThePageSets(): void {
		$this->assertFalse( $this->buildFor( [ 'autoRenderMainSubject' => false ] )['pageValues']['autoRenderMainSubject'] );
	}

	public function testGivesNoPageValueForASettingThePageDoesNotSet(): void {
		$this->assertNull( $this->buildFor( [ 'autoRenderMainSubject' => false ] )['pageValues']['subjectFirst'] );
	}

	public function testGivesNoPageValueForASettingThePageSetsToAValueItDoesNotAccept(): void {
		$this->assertNull( $this->buildFor( [ 'subjectFirst' => 'yes' ] )['pageValues']['subjectFirst'] );
	}

	public function testGivesTheLocalSettingsValueWhateverThePageSets(): void {
		$jsConfig = $this->buildFor(
			[ 'dataLanguages' => [ 'fr' ] ],
			[ 'NeoWikiDataLanguages' => [ 'de', 'it' ] ] + self::PHP_CONFIG
		);

		$this->assertSame( [ 'de', 'it' ], $jsConfig['localSettingsValues']['dataLanguages'] );
	}

}
