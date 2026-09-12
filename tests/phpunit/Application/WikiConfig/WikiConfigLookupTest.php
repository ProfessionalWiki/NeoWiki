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
		'NeoWikiDereferenceSubjectsToHostingPage' => false,
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
		$this->assertFalse( $this->newLookup( null )->getEffectiveValue( 'dereferenceSubjectsToHostingPage' ) );
	}

	public function testUsesThePhpValueWhenTheKeyIsAbsentFromThePage(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'dereferenceSubjectsToHostingPage' ) );
	}

	public function testAValidPageValueWinsOverThePhpValue(): void {
		$lookup = $this->newLookup( [ 'dereferenceSubjectsToHostingPage' => true ] );

		$this->assertTrue( $lookup->getEffectiveValue( 'dereferenceSubjectsToHostingPage' ) );
	}

	public function testEachSettingIsResolvedIndependently(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'autoRenderMainSubject' ) );
	}

	public function testAnInvalidPageValueFallsBackToThePhpValue(): void {
		$lookup = $this->newLookup( [ 'dereferenceSubjectsToHostingPage' => 'yes' ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'dereferenceSubjectsToHostingPage' ) );
	}

	public function testAnInvalidPageValueLogsAWarning(): void {
		$logger = new TestLogger();

		$this->newLookup( [ 'dereferenceSubjectsToHostingPage' => 'yes' ], logger: $logger )
			->getEffectiveValue( 'dereferenceSubjectsToHostingPage' );

		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testUnknownPageKeysAreToleratedOnRead(): void {
		$logger = new TestLogger();
		$lookup = $this->newLookup(
			[ 'dereferenceSubjectsToHostingPage' => true, 'someFutureKey' => 'whatever' ],
			logger: $logger
		);

		$this->assertTrue( $lookup->getEffectiveValue( 'dereferenceSubjectsToHostingPage' ) );
		$this->assertFalse( $logger->hasWarningRecords() );
	}

	public function testThePageIsIgnoredWhenInWikiConfigIsDisabled(): void {
		$lookup = $this->newLookup( [ 'dereferenceSubjectsToHostingPage' => true ], enabled: false );

		$this->assertFalse( $lookup->getEffectiveValue( 'dereferenceSubjectsToHostingPage' ) );
	}

	public function testTheConfigPageIsReadAtMostOncePerLookup(): void {
		$source = new StubWikiConfigSource( [ 'dereferenceSubjectsToHostingPage' => true ] );
		$lookup = new WikiConfigLookup(
			new ConfigSchema(),
			$source,
			new HashConfig( self::PHP_CONFIG ),
			true,
			new TestLogger()
		);

		$lookup->getEffectiveValue( 'dereferenceSubjectsToHostingPage' );
		$lookup->getEffectiveValue( 'autoRenderMainSubject' );

		$this->assertSame( 1, $source->readCount );
	}

}
