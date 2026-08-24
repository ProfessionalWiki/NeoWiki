<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Validation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\SeverityNormalizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Validation\SeverityNormalizer
 */
class SeverityNormalizerTest extends TestCase {

	public function testShorthandScalarPassesThroughWithNoSeverity(): void {
		[ $values, $severities ] = SeverityNormalizer::extract( [ 'maximum' => 100 ] );

		$this->assertSame( [ 'maximum' => 100 ], $values );
		$this->assertSame( [], $severities );
	}

	public function testObjectFormScalarIsUnwrapped(): void {
		[ $values, $severities ] = SeverityNormalizer::extract(
			[ 'maximum' => [ 'value' => 100, 'severity' => 'error' ] ]
		);

		$this->assertSame( [ 'maximum' => 100 ], $values );
		$this->assertSame( [ 'maximum' => Severity::Error ], $severities );
	}

	public function testObjectFormBooleanImpliesTrue(): void {
		[ $values, $severities ] = SeverityNormalizer::extract(
			[ 'required' => [ 'severity' => 'error' ] ]
		);

		$this->assertSame( [ 'required' => true ], $values );
		$this->assertSame( [ 'required' => Severity::Error ], $severities );
	}

	public function testReservedKeyCarryingSeverityIsNotNormalized(): void {
		// 'high' is not a severity: normalizing this key would throw rather than pass it through.
		$raw = [ 'type' => 'number', 'description' => 'x', 'default' => [ 'severity' => 'high' ] ];

		[ $values, $severities ] = SeverityNormalizer::extract( $raw );

		$this->assertSame( $raw, $values );
		$this->assertSame( [], $severities );
	}

	public function testUnknownSeverityThrows(): void {
		$this->expectException( InvalidArgumentException::class );
		SeverityNormalizer::extract( [ 'maximum' => [ 'value' => 1, 'severity' => 'bogus' ] ] );
	}

	public function testApplyWrapsErrorScalarAndSkipsWarning(): void {
		$json = [ 'minimum' => 0, 'maximum' => 100 ];
		$severities = [ 'minimum' => Severity::Warning, 'maximum' => Severity::Error ];

		$this->assertSame(
			[ 'minimum' => 0, 'maximum' => [ 'value' => 100, 'severity' => 'error' ] ],
			SeverityNormalizer::apply( $json, $severities )
		);
	}

	public function testApplyWrapsErrorBooleanWithoutValue(): void {
		$this->assertSame(
			[ 'required' => [ 'severity' => 'error' ] ],
			SeverityNormalizer::apply( [ 'required' => true ], [ 'required' => Severity::Error ] )
		);
	}

	public function testExplicitNullValueIsPreservedRatherThanCoalescedToTrue(): void {
		[ $values, ] = SeverityNormalizer::extract(
			[ 'maximum' => [ 'value' => null, 'severity' => 'error' ] ]
		);

		$this->assertNull( $values['maximum'] );
	}

	public function testExtractApplyRoundTripsErrorAnnotations(): void {
		$original = [ 'options' => [ 'value' => [ 'a', 'b' ], 'severity' => 'error' ] ];

		[ $values, $severities ] = SeverityNormalizer::extract( $original );

		$this->assertSame( $original, SeverityNormalizer::apply( $values, $severities ) );
	}

	/**
	 * The value-less object form implies true, so emitting it for a `false` value would read
	 * back as `true` and silently enable a Constraint the author disabled. The content schema
	 * constrains the core booleans; a custom Property Type's own boolean key carries no such guard.
	 */
	public function testApplyKeepsFalseBooleanValueRatherThanImplyingTrue(): void {
		$this->assertSame(
			[ 'caseSensitive' => [ 'value' => false, 'severity' => 'error' ] ],
			SeverityNormalizer::apply( [ 'caseSensitive' => false ], [ 'caseSensitive' => Severity::Error ] )
		);
	}

	public function testFalseBooleanCustomKeyRoundTrips(): void {
		$original = [ 'caseSensitive' => [ 'value' => false, 'severity' => 'error' ] ];

		[ $values, $severities ] = SeverityNormalizer::extract( $original );

		$this->assertFalse( $values['caseSensitive'] );
		$this->assertSame( $original, SeverityNormalizer::apply( $values, $severities ) );
	}
}
