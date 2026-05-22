<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\BooleanType
 */
class BooleanPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "boolean",
	"description": "",
	"required": false,
	"default": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "boolean"
}
JSON
			)
		);
	}

	public function testFullSerializationWithTrueDefaultIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "boolean",
	"description": "Is published",
	"required": true,
	"default": true
}
JSON
		);
	}

	public function testFullSerializationWithFalseDefaultIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "boolean",
	"description": "",
	"required": false,
	"default": false
}
JSON
		);
	}

	public function testExceptionOnNonBooleanDefault(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "boolean",
	"default": "yes"
}
JSON
		);
	}

	public function testGetPropertyTypeIsBoolean(): void {
		$property = $this->fromJson(
			<<<JSON
{
	"type": "boolean"
}
JSON
		);

		$this->assertInstanceOf( BooleanProperty::class, $property );
		$this->assertSame( 'boolean', $property->getPropertyType() );
	}

	public function testDoesNotAllowMultipleValues(): void {
		$property = $this->fromJson(
			<<<JSON
{
	"type": "boolean"
}
JSON
		);

		$this->assertFalse( $property->allowsMultipleValues() );
	}

}
