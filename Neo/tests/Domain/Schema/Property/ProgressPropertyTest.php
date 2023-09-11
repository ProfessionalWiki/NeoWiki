<?php

declare( strict_types = 1 );

namespace Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property\PropertyTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\ProgressProperty
 */
class ProgressPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"format": "progress",
	"description": "",
	"required": false,
	"default": null,
	"minimum": 0,
	"maximum": 100,
	"step": 1
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"format": "progress"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "progress",
	"description": "foo",
	"required": true,
	"default": 42,
	"minimum": 1,
	"maximum": 99,
	"step": 23
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "progress",
	"description": "",
	"required": false,
	"default": null,
	"minimum": 0,
	"maximum": 100,
	"step": 1
}
JSON
		);
	}

	public function testExceptionOnInvalidMax(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"format": "progress",
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
	"format": "progress",
	"minimum": "yes"
}
JSON
		);
	}

	public function testExceptionOnInvalidStep(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"format": "progress",
	"step": "yes"
}
JSON
		);
	}

}
