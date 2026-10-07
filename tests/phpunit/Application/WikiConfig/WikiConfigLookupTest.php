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
		'NeoWikiSubjectFirst' => false,
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
		$this->assertFalse( $this->newLookup( null )->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testUsesThePhpValueWhenTheKeyIsAbsentFromThePage(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testAValidPageValueWinsOverThePhpValue(): void {
		$lookup = $this->newLookup( [ 'subjectFirst' => true ] );

		$this->assertTrue( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testEachSettingIsResolvedIndependently(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'autoRenderMainSubject' ) );
	}

	public function testAnInvalidPageValueFallsBackToThePhpValue(): void {
		$lookup = $this->newLookup( [ 'subjectFirst' => 'yes' ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testAnInvalidPageValueLogsAWarning(): void {
		$logger = new TestLogger();

		$this->newLookup( [ 'subjectFirst' => 'yes' ], logger: $logger )
			->getEffectiveValue( 'subjectFirst' );

		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testUnknownPageKeysAreToleratedOnRead(): void {
		$logger = new TestLogger();
		$lookup = $this->newLookup(
			[ 'subjectFirst' => true, 'someFutureKey' => 'whatever' ],
			logger: $logger
		);

		$this->assertTrue( $lookup->getEffectiveValue( 'subjectFirst' ) );
		$this->assertFalse( $logger->hasWarningRecords() );
	}

	public function testThePageIsIgnoredWhenInWikiConfigIsDisabled(): void {
		$lookup = $this->newLookup( [ 'subjectFirst' => true ], enabled: false );

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testTheConfigPageIsReadAtMostOncePerLookup(): void {
		$source = new StubWikiConfigSource( [ 'subjectFirst' => true ] );
		$lookup = new WikiConfigLookup(
			new ConfigSchema(),
			$source,
			new HashConfig( self::PHP_CONFIG ),
			true,
			new TestLogger()
		);

		$lookup->getEffectiveValue( 'subjectFirst' );
		$lookup->getEffectiveValue( 'autoRenderMainSubject' );

		$this->assertSame( 1, $source->readCount );
	}

}
