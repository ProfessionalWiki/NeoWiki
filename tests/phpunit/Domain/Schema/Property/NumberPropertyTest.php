<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;

#[CoversClass( NumberProperty::class )]
class NumberPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "number"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "number",
	"description": "foo",
	"required": true,
	"default": 42,
	"precision": 2,
	"minimum": 0,
	"maximum": 100
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON
		);
	}

	public function testExceptionOnInvalidPrecision(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "number",
	"precision": "yes"
}
JSON
		);
	}

	public function testExceptionOnInvalidMax(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "number",
	"maximum": "yes"
}
JSON
		);
	}

	public function testExceptionOnInvalidMin(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "number",
	"minimum": "yes"
}
JSON
		);
	}

}
