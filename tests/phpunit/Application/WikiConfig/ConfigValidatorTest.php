<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\WikiConfig\ConfigValidator
 */
class ConfigValidatorTest extends TestCase {

	/**
	 * @return array[]
	 */
	private function validate( string $json ): array {
		return ( new ConfigValidator( new ConfigSchema() ) )->validate( $json );
	}

	public function testEmptyObjectIsValid(): void {
		$this->assertSame( [], $this->validate( '{}' ) );
	}

	public function testBothSettingsPopulatedIsValid(): void {
		$this->assertSame(
			[],
			$this->validate( '{ "dereferenceSubjectsToDataTab": true, "autoRenderMainSubject": false }' )
		);
	}

	public function testSyntaxErrorIsLeftToCore(): void {
		$this->assertSame( [], $this->validate( '{ not valid json' ) );
	}

	public function testLiteralNullIsRejectedAsNotAnObject(): void {
		$this->assertSame( [ [ ConfigValidator::ERROR_NOT_OBJECT ] ], $this->validate( 'null' ) );
	}

	public function testJsonArrayIsRejectedAsNotAnObject(): void {
		$this->assertSame( [ [ ConfigValidator::ERROR_NOT_OBJECT ] ], $this->validate( '[ 1, 2 ]' ) );
	}

	public function testScalarJsonIsRejectedAsNotAnObject(): void {
		$this->assertSame( [ [ ConfigValidator::ERROR_NOT_OBJECT ] ], $this->validate( '42' ) );
	}

	public function testUnknownKeyIsRejected(): void {
		$this->assertSame(
			[ [ ConfigValidator::ERROR_UNKNOWN_KEY, 'NeoWikiSparqlStores' ] ],
			$this->validate( '{ "NeoWikiSparqlStores": [] }' )
		);
	}

	public function testWrongTypeForBooleanSettingIsRejected(): void {
		$this->assertSame(
			[ [ 'neowiki-config-error-invalid-boolean', 'autoRenderMainSubject' ] ],
			$this->validate( '{ "autoRenderMainSubject": "yes" }' )
		);
	}

	public function testWrongTypeForDereferenceSettingIsRejected(): void {
		$this->assertSame(
			[ [ 'neowiki-config-error-invalid-boolean', 'dereferenceSubjectsToDataTab' ] ],
			$this->validate( '{ "dereferenceSubjectsToDataTab": "yes" }' )
		);
	}

	public function testEveryErrorIsReported(): void {
		$this->assertEqualsCanonicalizing(
			[
				[ ConfigValidator::ERROR_UNKNOWN_KEY, 'nope' ],
				[ 'neowiki-config-error-invalid-boolean', 'dereferenceSubjectsToDataTab' ],
			],
			$this->validate( '{ "nope": 1, "dereferenceSubjectsToDataTab": "x" }' )
		);
	}

}
