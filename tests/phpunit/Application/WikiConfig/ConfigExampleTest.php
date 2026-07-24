<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigExample;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigExample
 */
class ConfigExampleTest extends TestCase {

	public function testExampleIsSyntacticallyValidJson(): void {
		json_decode( ConfigExample::JSON, true );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
	}

	public function testPreloadedExamplePassesTheValidator(): void {
		$this->assertSame(
			[],
			( new ConfigValidator( new ConfigSchema() ) )->validate( ConfigExample::JSON )
		);
	}

	public function testExampleCoversEverySetting(): void {
		$data = json_decode( ConfigExample::JSON, true );

		foreach ( ( new ConfigSchema() )->getSettings() as $setting ) {
			$this->assertArrayHasKey( $setting->pageKey, $data );
		}
	}

}
