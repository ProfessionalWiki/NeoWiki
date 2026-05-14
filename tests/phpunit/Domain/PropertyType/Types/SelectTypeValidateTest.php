<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType
 */
class SelectTypeValidateTest extends TestCase {

	private SelectType $type;

	protected function setUp(): void {
		$this->type = new SelectType();
	}

	public function testOptionalAndEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue(),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue(),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testNonStringValueReturnsRequiredWhenRequired(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testValidOptionReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'red' ),
			$this->newProperty( required: false, multiple: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testInvalidOptionReturnsIndexedViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'purple' ),
			$this->newProperty( required: false, multiple: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-option', $violations[0]->code );
		$this->assertSame( [ 'purple' ], $violations[0]->args );
		$this->assertSame( 0, $violations[0]->valuePartIndex );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testMultipleInvalidOptionsReturnsIndexedViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'red', 'purple', 'green', 'mauve' ),
			$this->newProperty( required: false, multiple: true ),
		);

		$this->assertCount( 2, $violations );

		$this->assertSame( 'invalid-option', $violations[0]->code );
		$this->assertSame( [ 'purple' ], $violations[0]->args );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
		$this->assertNull( $violations[0]->propertyName );

		$this->assertSame( 'invalid-option', $violations[1]->code );
		$this->assertSame( [ 'mauve' ], $violations[1]->args );
		$this->assertSame( 3, $violations[1]->valuePartIndex );
		$this->assertNull( $violations[1]->propertyName );
	}

	public function testSingleValueOnlyWhenMultipleIsFalseAndMoreThanOnePart(): void {
		$violations = $this->type->validate(
			new StringValue( 'red', 'green' ),
			$this->newProperty( required: false, multiple: false ),
		);

		$codes = array_map( static fn( $v ) => $v->code, $violations );
		$this->assertContains( 'single-value-only', $codes );

		$singleViolation = array_values( array_filter( $violations, static fn( $v ) => $v->code === 'single-value-only' ) )[0];
		$this->assertNull( $singleViolation->valuePartIndex );
		$this->assertNull( $singleViolation->propertyName );
	}

	public function testMultipleTrueAndManyValidPartsReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'red', 'green', 'blue' ),
			$this->newProperty( required: false, multiple: true ),
		);

		$this->assertSame( [], $violations );
	}

	private function newProperty( bool $required, bool $multiple = true ): SelectProperty {
		return SelectProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[
				'options' => [
					[ 'id' => 'red', 'label' => 'Red' ],
					[ 'id' => 'green', 'label' => 'Green' ],
					[ 'id' => 'blue', 'label' => 'Blue' ],
				],
				'multiple' => $multiple,
			],
		);
	}

}
