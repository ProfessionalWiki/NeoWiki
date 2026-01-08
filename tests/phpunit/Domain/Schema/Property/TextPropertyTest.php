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
	"uniqueItems": false
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
	"uniqueItems": true
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
	"uniqueItems": false
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

}
