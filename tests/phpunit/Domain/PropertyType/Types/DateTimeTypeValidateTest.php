<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
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
		$this->assertSame( Severity::Error, $violations[0]->severity );
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
		$this->assertSame( Severity::Warning, $violations[0]->severity );
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
		$this->assertSame( Severity::Warning, $violations[0]->severity );
	}

	/**
	 * Unlike NumberType, the bound severities reach checkMinimum()/checkMaximum() as arguments
	 * rather than being read at the raise site. Each test therefore leaves the other bound
	 * unannotated, so swapping the two arguments hands the violation the sibling's warning.
	 */
	public function testMinValueViolationUsesErrorWhenMinimumAnnotated(): void {
		$definition = PropertyDefinition::fromJson(
			[
				'type' => 'dateTime',
				'minimum' => [ 'value' => '2025-01-01T00:00:00Z', 'severity' => 'error' ],
				'maximum' => '2025-12-31T23:59:59Z',
			],
			PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
		);

		$violations = $this->type->validate( new StringValue( '2024-12-31T23:59:59Z' ), $definition );

		$this->assertSame( 'min-value', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testMaxValueViolationUsesErrorWhenMaximumAnnotated(): void {
		$definition = PropertyDefinition::fromJson(
			[
				'type' => 'dateTime',
				'minimum' => '2025-01-01T00:00:00Z',
				'maximum' => [ 'value' => '2025-12-31T23:59:59Z', 'severity' => 'error' ],
			],
			PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
		);

		$violations = $this->type->validate( new StringValue( '2026-01-01T00:00:00Z' ), $definition );

		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
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
