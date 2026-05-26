<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\RedHerb\ColorProperty;
use ProfessionalWiki\RedHerb\ColorType;

/**
 * @covers \ProfessionalWiki\RedHerb\ColorType
 */
class ColorTypeValidateTest extends TestCase {

	private ColorType $type;

	protected function setUp(): void {
		$this->type = new ColorType();
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new StringValue(),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testValidHexReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( '#aabbcc' ),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testInvalidHexReturnsInvalidColorViolation(): void {
		$violations = $this->type->validate(
			new StringValue( '#xxx' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-color', $violations[0]->code );
		$this->assertSame( [ '#xxx' ], $violations[0]->args );
		$this->assertSame( 0, $violations[0]->valuePartIndex );
	}

	public function testMultipleInvalidHexProducesIndexedViolations(): void {
		$violations = $this->type->validate(
			new StringValue( '#aabbcc', 'red', '#xxx' ),
			$this->newProperty( required: false ),
		);

		$this->assertCount( 2, $violations );
		$this->assertSame( 'invalid-color', $violations[0]->code );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
		$this->assertSame( 'invalid-color', $violations[1]->code );
		$this->assertSame( 2, $violations[1]->valuePartIndex );
	}

	public function testNonStringValueReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new NumberValue( 42 ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testAllowedColorsRestrictsToAllowList(): void {
		$violations = $this->type->validate(
			new StringValue( '#aabbcc', '#112233' ),
			$this->newProperty( required: false, allowedColors: [ '#aabbcc' ] ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'invalid-option', $violations[0]->code );
		$this->assertSame( [ '#112233' ], $violations[0]->args );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
	}

	/**
	 * @param list<string> $allowedColors
	 */
	private function newProperty( bool $required, array $allowedColors = [] ): ColorProperty {
		return ColorProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'allowedColors' => $allowedColors ],
		);
	}

}
