<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType
 */
class TextTypeValidateTest extends TestCase {

	private TextType $type;

	protected function setUp(): void {
		$this->type = new TextType();
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

	public function testRequiredAndNonEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'hello' ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testOptionalAndEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue(),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testNonStringValueReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: true, uniqueItems: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testUniqueItemsWithDuplicatesReturnsUniqueViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'foo', 'bar', 'foo' ),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'unique', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testUniqueItemsWithAllDistinctReturnsNoViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'foo', 'bar', 'baz' ),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testUniqueItemsFalseWithDuplicatesReturnsNoViolation(): void {
		$violations = $this->type->validate(
			new StringValue( 'foo', 'foo' ),
			$this->newProperty( required: false, uniqueItems: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndUniqueViolationsBothReturnedWhenBothConditionsTrigger(): void {
		$violations = $this->type->validate(
			new StringValue( '', '' ),
			$this->newProperty( required: true, uniqueItems: true ),
		);

		$codes = array_map( fn( $v ) => $v->code, $violations );
		$this->assertContains( 'required', $codes );
		$this->assertContains( 'unique', $codes );
	}

	private function newProperty(
		bool $required,
		bool $uniqueItems = false,
	): TextProperty {
		return TextProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'multiple' => true, 'uniqueItems' => $uniqueItems ],
		);
	}

}
