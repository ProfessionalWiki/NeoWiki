<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType
 */
class DateTypeValidateTest extends TestCase {

	private DateType $type;

	protected function setUp(): void {
		$this->type = new DateType();
	}

	public function testOptionalAndUndefinedReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new NumberValue( 0 ),
			$this->newProperty( required: false ),
		) );
	}

	public function testRequiredAndUndefinedReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new NumberValue( 0 ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testRequiredAndEmptyStringPartReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '' ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testValidDateWithinBoundsReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '2025-06-15' ),
			$this->newProperty( required: false, minimum: '2020-01-01', maximum: '2030-12-31' ),
		) );
	}

	public function testUnparseableStringReturnsInvalidDate(): void {
		$violations = $this->type->validate(
			new StringValue( 'not-a-date' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-date', $violations[0]->code );
	}

	public function testYearOnlyReturnsInvalidDate(): void {
		$violations = $this->type->validate(
			new StringValue( '2025' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-date', $violations[0]->code );
	}

	public function testYearMonthReturnsInvalidDate(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-date', $violations[0]->code );
	}

	public function testValueWithTimeComponentReturnsInvalidDate(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-06-15T12:00:00Z' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-date', $violations[0]->code );
	}

	public function testCalendarOverflowReturnsInvalidDate(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-02-30' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-date', $violations[0]->code );
	}

	public function testFeb29InNonLeapYearReturnsInvalidDate(): void {
		$violations = $this->type->validate(
			new StringValue( '2025-02-29' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-date', $violations[0]->code );
	}

	public function testFeb29InLeapYearReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '2024-02-29' ),
			$this->newProperty( required: false ),
		) );
	}

	public function testBeforeMinimumReturnsMinValue(): void {
		$violations = $this->type->validate(
			new StringValue( '2024-12-31' ),
			$this->newProperty( required: false, minimum: '2025-01-01' ),
		);

		$this->assertSame( 'min-value', $violations[0]->code );
		$this->assertSame( [ '2025-01-01' ], $violations[0]->args );
	}

	public function testAfterMaximumReturnsMaxValue(): void {
		$violations = $this->type->validate(
			new StringValue( '2026-01-01' ),
			$this->newProperty( required: false, maximum: '2025-12-31' ),
		);

		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( [ '2025-12-31' ], $violations[0]->args );
	}

	public function testEqualToBoundsReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '2025-06-15' ),
			$this->newProperty( required: false, minimum: '2025-06-15', maximum: '2025-06-15' ),
		) );
	}

	private function newProperty(
		bool $required,
		?string $minimum = null,
		?string $maximum = null,
	): DateProperty {
		return DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'minimum' => $minimum, 'maximum' => $maximum ],
		);
	}

}
