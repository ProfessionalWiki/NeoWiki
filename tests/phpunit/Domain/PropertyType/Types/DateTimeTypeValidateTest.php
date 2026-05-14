<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType
 */
class DateTimeTypeValidateTest extends TestCase {

	private DateTimeType $type;

	protected function setUp(): void {
		$this->type = new DateTimeType();
	}

	public function testOptionalAndUndefinedReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndUndefinedReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testRequiredAndEmptyStringPartReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '' ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testValidDatetimeWithinBoundsReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00Z' ),
			$this->newProperty(
				required: false,
				minimum: '2020-01-01T00:00:00Z',
				maximum: '2030-12-31T23:59:59Z',
			),
		);

		$this->assertSame( [], $violations );
	}

	public function testInvalidDatetimeReturnsInvalidDatetime(): void {
		$violations = $this->type->validate(
			new StringValue( 'not-a-date' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-datetime', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testYearOnlyReturnsInvalidDatetime(): void {
		$violations = $this->type->validate(
			new StringValue( '2025' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-datetime', $violations[0]->code );
	}

	public function testYearMonthReturnsInvalidDatetime(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-datetime', $violations[0]->code );
	}

	public function testDateOnlyReturnsInvalidDatetime(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-datetime', $violations[0]->code );
	}

	public function testCalendarOverflowReturnsInvalidDatetime(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-02-30T00:00:00Z' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-datetime', $violations[0]->code );
	}

	public function testMissingOffsetReturnsInvalidDatetime(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-datetime', $violations[0]->code );
	}

	public function testAcceptsExplicitPositiveOffset(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00+02:00' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testAcceptsExplicitNegativeOffset(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00-05:00' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testAcceptsFractionalSecondsWithZ(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00.123Z' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testAcceptsNanosecondPrecisionFractionalSeconds(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00.123456789Z' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testBeforeMinimumReturnsMinValue(): void {
		$violations = $this->type->validate(
			new StringValue( '2024-12-31T23:59:59Z' ),
			$this->newProperty( required: false, minimum: '2025-01-01T00:00:00Z' ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'min-value', $violations[0]->code );
		$this->assertSame( [ '2025-01-01T00:00:00Z' ], $violations[0]->args );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testAfterMaximumReturnsMaxValue(): void {
		$violations = $this->type->validate(
			new StringValue( '2026-01-01T00:00:00Z' ),
			$this->newProperty( required: false, maximum: '2025-12-31T23:59:59Z' ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( [ '2025-12-31T23:59:59Z' ], $violations[0]->args );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testEqualToBoundsReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00Z' ),
			$this->newProperty(
				required: false,
				minimum: '2025-06-15T12:00:00Z',
				maximum: '2025-06-15T12:00:00Z',
			),
		);

		$this->assertSame( [], $violations );
	}

	private function newProperty(
		bool $required,
		?string $minimum = null,
		?string $maximum = null,
	): DateTimeProperty {
		return DateTimeProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'minimum' => $minimum, 'maximum' => $maximum ],
		);
	}

}
