<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Validation\Violation
 */
class ViolationTest extends TestCase {

	public function testConstructsWithAllFields(): void {
		$violation = new Violation(
			propertyName: new PropertyName( 'Website' ),
			code: 'invalid-url',
			args: [ 'bad' ],
			valuePartIndex: 2,
		);

		$this->assertEquals( new PropertyName( 'Website' ), $violation->propertyName );
		$this->assertSame( 'invalid-url', $violation->code );
		$this->assertSame( [ 'bad' ], $violation->args );
		$this->assertSame( 2, $violation->valuePartIndex );
	}

	public function testConstructsWithDefaults(): void {
		$violation = new Violation( propertyName: null, code: 'required' );

		$this->assertNull( $violation->propertyName );
		$this->assertSame( 'required', $violation->code );
		$this->assertSame( [], $violation->args );
		$this->assertNull( $violation->valuePartIndex );
	}

	public function testWithPropertyNameSetsPropertyName(): void {
		$original = new Violation( propertyName: null, code: 'required' );
		$named = $original->withPropertyName( new PropertyName( 'Email' ) );

		$this->assertEquals( new PropertyName( 'Email' ), $named->propertyName );
	}

	public function testWithPropertyNameDoesNotMutateOriginal(): void {
		$original = new Violation( propertyName: null, code: 'required' );
		$named = $original->withPropertyName( new PropertyName( 'Email' ) );

		$this->assertNull( $original->propertyName );
		$this->assertNotSame( $original, $named );
	}

	public function testWithPropertyNamePreservesOtherFields(): void {
		$original = new Violation(
			propertyName: null,
			code: 'invalid-url',
			args: [ 'bad' ],
			valuePartIndex: 1,
		);

		$named = $original->withPropertyName( new PropertyName( 'Website' ) );

		$this->assertSame( 'invalid-url', $named->code );
		$this->assertSame( [ 'bad' ], $named->args );
		$this->assertSame( 1, $named->valuePartIndex );
	}

	public function testErrorSeverityIsBlocking(): void {
		$violation = new Violation( propertyName: null, code: 'required', severity: Severity::Error );

		$this->assertTrue( $violation->isBlocking() );
	}

	public function testWarningSeverityIsNotBlocking(): void {
		$violation = new Violation( propertyName: null, code: 'required', severity: Severity::Warning );

		$this->assertFalse( $violation->isBlocking() );
	}

	public function testDefaultSeverityIsWarning(): void {
		$violation = new Violation( propertyName: null, code: 'required' );

		$this->assertSame( Severity::Warning, $violation->severity );
	}

	public function testWithPropertyNamePreservesSeverity(): void {
		$original = new Violation( propertyName: null, code: 'max-value', severity: Severity::Error );

		$this->assertSame( Severity::Error, $original->withPropertyName( new PropertyName( 'Age' ) )->severity );
	}

}
