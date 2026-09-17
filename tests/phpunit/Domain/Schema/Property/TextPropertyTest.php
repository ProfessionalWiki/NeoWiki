<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty
 */
class TextPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "text",
	"description": "",
	"required": false,
	"default": null,
	"multiple": false,
	"uniqueItems": false,
	"minLength": null,
	"maxLength": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "text"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "text",
	"description": "foo",
	"required": true,
	"default": 42,
	"multiple": true,
	"uniqueItems": true,
	"minLength": 2,
	"maxLength": 40
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "text",
	"description": "",
	"required": false,
	"default": null,
	"multiple": false,
	"uniqueItems": false,
	"minLength": null,
	"maxLength": null
}
JSON
		);
	}

	public function testExceptionOnInvalidMultiple(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "text",
	"multiple": 42
}
JSON
		);
	}

	public function testExceptionOnInvalidUniqueItems(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "text",
	"uniqueItems": "maybe"
}
JSON
		);
	}

	public function testExceptionOnInvalidMinLength(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson( '{ "type": "text", "minLength": "yes" }' );
	}

	public function testValueIsAnArrayOfStrings(): void {
		$this->assertSame(
			[ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			TestProperty::buildText( multiple: true )->toJsonSchema()
		);
	}

	public function testSingleValuedPropertyAdmitsOnePart(): void {
		$this->assertSame(
			[ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'maxItems' => 1 ],
			TestProperty::buildText( multiple: false )->toJsonSchema()
		);
	}

	public function testRequiredPropertyNeedsAtLeastOnePart(): void {
		$this->assertSame(
			[ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'minItems' => 1 ],
			TestProperty::buildText( multiple: true, required: true )->toJsonSchema()
		);
	}

	public function testLengthConstraintsBoundEachPart(): void {
		$value = TestProperty::buildText( minLength: 10, maxLength: 100 )->toJsonSchema();

		$this->assertSame( [ 'type' => 'string', 'minLength' => 10, 'maxLength' => 100 ], $value['items'] );
	}

	public function testUniqueItemsConstraintForbidsDuplicateParts(): void {
		$this->assertSame(
			[ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'uniqueItems' => true ],
			TestProperty::buildText( multiple: true, uniqueItems: true )->toJsonSchema()
		);
	}

	public function testConstraintSeverityIsNotExpressed(): void {
		$blocking = TestProperty::buildText(
			required: true,
			maxLength: 5,
			constraintSeverities: [ 'required' => Severity::Error, 'maxLength' => Severity::Error ],
		);

		$this->assertSame(
			[
				'type' => 'array',
				'items' => [ 'type' => 'string', 'maxLength' => 5 ],
				'maxItems' => 1,
				'minItems' => 1,
			],
			$blocking->toJsonSchema(),
			'A JSON Schema is binary: severity decides whether a write is blocked, not whether a value is valid.'
		);
	}

}
