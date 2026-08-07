<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
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
		$this->assertSame( Severity::Error, $violations[0]->severity );
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
		$this->assertSame( Severity::Warning, $violations[0]->severity );
	}

	public function testAfterMaximumReturnsMaxValue(): void {
		$violations = $this->type->validate(
			new StringValue( '2026-01-01' ),
			$this->newProperty( required: false, maximum: '2025-12-31' ),
		);

		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( [ '2025-12-31' ], $violations[0]->args );
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
				'type' => 'date',
				'minimum' => [ 'value' => '2025-01-01', 'severity' => 'error' ],
				'maximum' => '2025-12-31',
			],
			PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
		);

		$violations = $this->type->validate( new StringValue( '2024-12-31' ), $definition );

		$this->assertSame( 'min-value', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testMaxValueViolationUsesErrorWhenMaximumAnnotated(): void {
		$definition = PropertyDefinition::fromJson(
			[
				'type' => 'date',
				'minimum' => '2025-01-01',
				'maximum' => [ 'value' => '2025-12-31', 'severity' => 'error' ],
			],
			PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY ),
		);

		$violations = $this->type->validate( new StringValue( '2026-01-01' ), $definition );

		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
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
