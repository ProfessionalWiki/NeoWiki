<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;

#[CoversClass( TextProperty::class )]
class TextPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"format": "text",
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
	"format": "text"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "text",
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
	"format": "text",
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
	"format": "text",
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
	"format": "text",
	"uniqueItems": "maybe"
}
JSON
		);
	}

}
