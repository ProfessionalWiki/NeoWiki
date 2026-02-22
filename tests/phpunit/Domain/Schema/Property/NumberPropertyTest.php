<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty
 */
class NumberPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"constraints": {
		"minimum": null,
		"maximum": null
	},
	"displayAttributes": {
		"precision": null
	}
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
	"constraints": {
		"minimum": 0,
		"maximum": 100
	},
	"displayAttributes": {
		"precision": 2
	}
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
	"constraints": {
		"minimum": null,
		"maximum": null
	},
	"displayAttributes": {
		"precision": null
	}
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
	"displayAttributes": {
		"precision": "yes"
	}
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
	"constraints": {
		"maximum": "yes"
	}
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
	"constraints": {
		"minimum": "yes"
	}
}
JSON
		);
	}

}
