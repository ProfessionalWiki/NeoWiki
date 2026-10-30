<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\EdtfType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\EdtfProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\EdtfType
 */
class EdtfTypeValidateTest extends TestCase {

	private EdtfType $type;

	protected function setUp(): void {
		$this->type = new EdtfType();
	}

	public function testValidDateReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '1985-04-12' ),
			$this->newProperty( required: false ),
		) );
	}

	public function testValidUncertainDateReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '1985?' ),
			$this->newProperty( required: false ),
		) );
	}

	public function testValidIntervalReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '1964/2008' ),
			$this->newProperty( required: false ),
		) );
	}

	public function testValidSetReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new StringValue( '{2010,2015..2020}' ),
			$this->newProperty( required: false ),
		) );
	}

	public function testInvalidStringReturnsInvalidEdtf(): void {
		$violations = $this->type->validate(
			new StringValue( 'not-a-date' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( 'invalid-edtf', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '' ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testRequiredAndWrongShapeReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new NumberValue( 0 ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( 'required', $violations[0]->code );
	}

	private function newProperty( bool $required ): EdtfProperty {
		return EdtfProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[],
		);
	}

}
