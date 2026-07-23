<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\WikiConfig;

use MediaWiki\Config\HashConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubWikiConfigSource;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\WikiConfigLookup
 */
class WikiConfigLookupTest extends TestCase {

	private const PHP_CONFIG = [
		'NeoWikiDereferenceSubjectsToDataTab' => false,
		'NeoWikiAutoRenderMainSubject' => true,
	];

	private function newLookup(
		?array $pageData,
		bool $enabled = true,
		?TestLogger $logger = null
	): WikiConfigLookup {
		return new WikiConfigLookup(
			new ConfigSchema(),
			new StubWikiConfigSource( $pageData ),
			new HashConfig( self::PHP_CONFIG ),
			$enabled,
			$logger ?? new TestLogger()
		);
	}

	public function testUsesThePhpValueWhenThereIsNoConfigPage(): void {
		$this->assertFalse( $this->newLookup( null )->getEffectiveValue( 'dereferenceSubjectsToDataTab' ) );
	}

	public function testUsesThePhpValueWhenTheKeyIsAbsentFromThePage(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'dereferenceSubjectsToDataTab' ) );
	}

	public function testAValidPageValueWinsOverThePhpValue(): void {
		$lookup = $this->newLookup( [ 'dereferenceSubjectsToDataTab' => true ] );

		$this->assertTrue( $lookup->getEffectiveValue( 'dereferenceSubjectsToDataTab' ) );
	}

	public function testEachSettingIsResolvedIndependently(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'autoRenderMainSubject' ) );
	}

	public function testAnInvalidPageValueFallsBackToThePhpValue(): void {
		$lookup = $this->newLookup( [ 'dereferenceSubjectsToDataTab' => 'yes' ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'dereferenceSubjectsToDataTab' ) );
	}

	public function testAnInvalidPageValueLogsAWarning(): void {
		$logger = new TestLogger();

		$this->newLookup( [ 'dereferenceSubjectsToDataTab' => 'yes' ], logger: $logger )
			->getEffectiveValue( 'dereferenceSubjectsToDataTab' );

		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testUnknownPageKeysAreToleratedOnRead(): void {
		$logger = new TestLogger();
		$lookup = $this->newLookup(
			[ 'dereferenceSubjectsToDataTab' => true, 'someFutureKey' => 'whatever' ],
			logger: $logger
		);

		$this->assertTrue( $lookup->getEffectiveValue( 'dereferenceSubjectsToDataTab' ) );
		$this->assertFalse( $logger->hasWarningRecords() );
	}

	public function testThePageIsIgnoredWhenInWikiConfigIsDisabled(): void {
		$lookup = $this->newLookup( [ 'dereferenceSubjectsToDataTab' => true ], enabled: false );

		$this->assertFalse( $lookup->getEffectiveValue( 'dereferenceSubjectsToDataTab' ) );
	}

	public function testTheConfigPageIsReadAtMostOncePerLookup(): void {
		$source = new StubWikiConfigSource( [ 'dereferenceSubjectsToDataTab' => true ] );
		$lookup = new WikiConfigLookup(
			new ConfigSchema(),
			$source,
			new HashConfig( self::PHP_CONFIG ),
			true,
			new TestLogger()
		);

		$lookup->getEffectiveValue( 'dereferenceSubjectsToDataTab' );
		$lookup->getEffectiveValue( 'autoRenderMainSubject' );

		$this->assertSame( 1, $source->readCount );
	}

}
