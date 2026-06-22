<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;

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

}
