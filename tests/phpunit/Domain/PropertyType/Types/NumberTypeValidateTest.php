<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType
 */
class NumberTypeValidateTest extends TestCase {

	private NumberType $type;

	protected function setUp(): void {
		$this->type = new NumberType();
	}

	public function testOptionalAndUndefinedReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'not a number' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndUndefinedReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'not a number' ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testWithinBoundsReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 50 ),
			$this->newProperty( required: false, minimum: 0, maximum: 100 ),
		);

		$this->assertSame( [], $violations );
	}

	public function testEqualToBoundsReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: false, minimum: 42, maximum: 42 ),
		);

		$this->assertSame( [], $violations );
	}

	public function testBelowMinimumReturnsMinValueViolation(): void {
		$violations = $this->type->validate(
			new NumberValue( -1 ),
			$this->newProperty( required: false, minimum: 0 ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'min-value', $violations[0]->code );
		$this->assertSame( [ 0 ], $violations[0]->args );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testAboveMaximumReturnsMaxValueViolation(): void {
		$violations = $this->type->validate(
			new NumberValue( 101 ),
			$this->newProperty( required: false, maximum: 100 ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( [ 100 ], $violations[0]->args );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testNoBoundsSetReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 99999 ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testMaxValueViolationDefaultsToWarningSeverity(): void {
		$violations = $this->type->validate(
			new NumberValue( 101 ),
			$this->newProperty( required: false, maximum: 100 ),
		);

		$this->assertSame( Severity::Warning, $violations[0]->severity );
	}

	public function testMaxValueViolationUsesErrorWhenMaximumAnnotated(): void {
		$definition = PropertyDefinition::fromJson(
			[ 'type' => 'number', 'maximum' => [ 'value' => 100, 'severity' => 'error' ] ],
			PropertyTypeRegistry::withCoreTypes(),
		);

		$violations = $this->type->validate( new NumberValue( 101 ), $definition );

		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	private function newProperty(
		bool $required,
		float|int|null $minimum = null,
		float|int|null $maximum = null,
	): NumberProperty {
		return NumberProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'minimum' => $minimum, 'maximum' => $maximum, 'precision' => null ],
		);
	}

}
